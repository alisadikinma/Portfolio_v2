// Pure sampling for the hero point cloud. No DOM: takes ImageData-shaped input so it is unit-testable.

export interface PixelGrid {
  data: ArrayLike<number>;
  width: number;
  height: number;
}

export interface Sampled {
  /** x,y pairs in -0.5..0.5 image space (y up). */
  pos: Float32Array;
  /** 4 random floats per point. */
  rnd: Float32Array;
  /** Contrast-stretched luminance 0..1, one per point. */
  lum: Float32Array;
}

const BG_TOLERANCE = 26;

/**
 * Turn an n×n pixel grid into points. Background = colour of the top-left pixel, removed by a
 * flood fill from every border pixel. Luminance is stretched between the 3rd and 97th percentile.
 */
export function samplePortrait(img: PixelGrid, n: number, random: () => number = Math.random): Sampled {
  const px = img.data;
  const bg = [px[0]!, px[1]!, px[2]!];
  const isBg = (i: number) =>
    Math.hypot(px[i]! - bg[0]!, px[i + 1]! - bg[1]!, px[i + 2]! - bg[2]!) < BG_TOLERANCE;
  const seen = new Uint8Array(n * n);
  const stack: number[] = [];
  for (let k = 0; k < n; k++) stack.push(k, (n - 1) * n + k, k * n, k * n + n - 1);
  while (stack.length) {
    const p = stack.pop()!;
    if (seen[p] || !isBg(p * 4)) continue;
    seen[p] = 1;
    const x = p % n, y = (p / n) | 0;
    if (x > 0) stack.push(p - 1);
    if (x < n - 1) stack.push(p + 1);
    if (y > 0) stack.push(p - n);
    if (y < n - 1) stack.push(p + n);
  }
  const pos: number[] = [], rnd: number[] = [], lum: number[] = [];
  for (let p = 0; p < n * n; p++) {
    if (seen[p]) continue;
    const i = p * 4;
    const l = (0.2126 * px[i]! + 0.7152 * px[i + 1]! + 0.0722 * px[i + 2]!) / 255;
    const x = p % n, y = (p / n) | 0;
    pos.push(x / n - 0.5 + (random() - 0.5) / n, 0.5 - y / n + (random() - 0.5) / n);
    rnd.push(random(), random(), random(), random());
    lum.push(l);
  }
  if (lum.length > 0) {
    const sorted = [...lum].sort((a, b) => a - b);
    const lo = sorted[Math.floor(sorted.length * 0.03)]!;
    const hi = sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * 0.97))]!;
    const span = hi - lo || 1;
    for (let k = 0; k < lum.length; k++) lum[k] = Math.min(1, Math.max(0, (lum[k]! - lo) / span));
  }
  return { pos: new Float32Array(pos), rnd: new Float32Array(rnd), lum: new Float32Array(lum) };
}

export interface BudgetInput {
  cores?: number;
  memory?: number;
  width: number;
}

/** Sample grid size (points scale with n²). Low-end devices get the smallest budget. */
export function pointBudget({ cores, memory, width }: BudgetInput): number {
  if ((cores !== undefined && cores <= 4) || (memory !== undefined && memory <= 4)) return 110;
  return width < 760 ? 160 : 250;
}
