import { inflateSync } from 'node:zlib'

export type Rgb = [number, number, number]

type Image = { width: number; height: number; data: Buffer }

/** Minimal 8-bit PNG decoder for Playwright screenshots (RGB or RGBA, no interlace). */
export function decodePng(buffer: Buffer): Image {
  let offset = 8
  let width = 0
  let height = 0
  let bitDepth = 0
  let colorType = 0
  const idat: Buffer[] = []

  while (offset < buffer.length) {
    const length = buffer.readUInt32BE(offset)
    offset += 4
    const type = buffer.toString('ascii', offset, offset + 4)
    offset += 4
    const data = buffer.subarray(offset, offset + length)
    offset += length + 4

    if (type === 'IHDR') {
      width = data.readUInt32BE(0)
      height = data.readUInt32BE(4)
      bitDepth = data[8]
      colorType = data[9]
    } else if (type === 'IDAT') {
      idat.push(data)
    } else if (type === 'IEND') {
      break
    }
  }

  const channels = colorType === 6 ? 4 : colorType === 2 ? 3 : 0
  if (bitDepth !== 8 || channels === 0) {
    throw new Error(`Unsupported PNG (depth ${bitDepth}, color type ${colorType})`)
  }

  const raw = inflateSync(Buffer.concat(idat))
  const stride = width * channels
  const out = Buffer.alloc(stride * height)
  let pos = 0

  for (let y = 0; y < height; y++) {
    const filter = raw[pos++]
    const line = raw.subarray(pos, pos + stride)
    pos += stride
    const rowStart = y * stride
    const previousStart = (y - 1) * stride

    for (let x = 0; x < stride; x++) {
      const a = x >= channels ? out[rowStart + x - channels] : 0
      const b = y > 0 ? out[previousStart + x] : 0
      const c = x >= channels && y > 0 ? out[previousStart + x - channels] : 0
      let value = line[x]

      switch (filter) {
        case 0:
          break
        case 1:
          value += a
          break
        case 2:
          value += b
          break
        case 3:
          value += (a + b) >> 1
          break
        case 4: {
          const p = a + b - c
          const pa = Math.abs(p - a)
          const pb = Math.abs(p - b)
          const pc = Math.abs(p - c)
          value += pa <= pb && pa <= pc ? a : pb <= pc ? b : c
          break
        }
        default:
          throw new Error(`Unsupported PNG filter ${filter}`)
      }

      out[rowStart + x] = value & 0xff
    }
  }

  return { width, height, data: out }
}

/** Average colour of a square region, ignoring fully transparent pixels. */
export function averageRegion(image: Image, x: number, y: number, size = 5): Rgb {
  const channels = image.data.length / (image.width * image.height)
  const startX = Math.max(0, Math.min(image.width - 1, Math.round(x)))
  const startY = Math.max(0, Math.min(image.height - 1, Math.round(y)))
  let r = 0
  let g = 0
  let b = 0
  let count = 0

  for (let py = startY; py < Math.min(image.height, startY + size); py++) {
    for (let px = startX; px < Math.min(image.width, startX + size); px++) {
      const index = (py * image.width + px) * channels
      if (channels === 4 && image.data[index + 3] === 0) continue
      r += image.data[index]
      g += image.data[index + 1]
      b += image.data[index + 2]
      count++
    }
  }

  if (count === 0) return [0, 0, 0]
  return [Math.round(r / count), Math.round(g / count), Math.round(b / count)]
}

function encodeSrgb(value: number) {
  const channel = Math.min(1, Math.max(0, value))
  return channel <= 0.0031308 ? 12.92 * channel : 1.055 * channel ** (1 / 2.4) - 0.055
}

function oklabToRgb(lightness: number, a: number, b: number): Rgb {
  const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3
  const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3
  const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3
  const linear = [
    4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s,
    -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s,
    -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s,
  ]
  return linear.map((channel) => Math.round(encodeSrgb(channel) * 255)) as Rgb
}

function numeric(token: string, percentScale = 1) {
  return token.endsWith('%') ? Number.parseFloat(token) / 100 * percentScale : Number.parseFloat(token)
}

/** Parses computed CSS colours in rgb(), oklab() or oklch() form. */
export function parseCssColor(value: string): Rgb {
  const rgb = value.match(/rgba?\(([^)]+)\)/)
  if (rgb) {
    const parts = rgb[1].split(/[\s,/]+/).filter(Boolean).map(Number)
    return [parts[0], parts[1], parts[2]]
  }

  const oklch = value.match(/oklch\(([^)]+)\)/)
  if (oklch) {
    const parts = oklch[1].split(/[\s,/]+/).filter(Boolean)
    const lightness = numeric(parts[0])
    const chroma = numeric(parts[1], 0.4)
    const hue = Number.parseFloat(parts[2]) || 0
    const radians = (hue * Math.PI) / 180
    return oklabToRgb(lightness, chroma * Math.cos(radians), chroma * Math.sin(radians))
  }

  const oklab = value.match(/oklab\(([^)]+)\)/)
  if (oklab) {
    const parts = oklab[1].split(/[\s,/]+/).filter(Boolean)
    return oklabToRgb(numeric(parts[0]), numeric(parts[1], 0.4), numeric(parts[2], 0.4))
  }

  throw new Error(`Cannot parse colour: ${value}`)
}

function channelLuminance(value: number) {
  const channel = value / 255
  return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4
}

export function relativeLuminance([r, g, b]: Rgb) {
  return 0.2126 * channelLuminance(r) + 0.7152 * channelLuminance(g) + 0.0722 * channelLuminance(b)
}

export function contrastRatio(a: Rgb, b: Rgb) {
  const first = relativeLuminance(a)
  const second = relativeLuminance(b)
  return (Math.max(first, second) + 0.05) / (Math.min(first, second) + 0.05)
}

export function hex([r, g, b]: Rgb) {
  return `#${[r, g, b].map((value) => value.toString(16).padStart(2, '0')).join('')}`
}
