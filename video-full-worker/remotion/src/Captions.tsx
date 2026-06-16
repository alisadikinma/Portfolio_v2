import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig, interpolate, z } from 'remotion';

export const captionsSchema = z.object({
  cues: z.array(z.object({ start: z.number(), end: z.number(), text: z.string() })),
  durationInSeconds: z.number(),
});

type Props = z.infer<typeof captionsSchema>;

/**
 * Transparent overlay of timed Indonesian captions. Each active cue renders in an
 * OPAQUE bottom bar — sized to also cover the source's burned-in English subtitle
 * (the "timpa" decision). Composited over the assembled video by compose.js.
 */
export const Captions: React.FC<Props> = ({ cues }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const t = frame / fps;
  const active = cues.find((c) => t >= c.start && t < c.end);
  if (!active) return <AbsoluteFill />;

  const local = (t - active.start) * fps;
  const appear = interpolate(local, [0, 6], [0, 1], { extrapolateRight: 'clamp' });

  return (
    <AbsoluteFill style={{ justifyContent: 'flex-end', alignItems: 'center', paddingBottom: 360 }}>
      <div
        style={{
          maxWidth: 940,
          margin: '0 75px',
          padding: '22px 30px',
          background: 'rgba(5,5,6,0.86)',
          borderRadius: 18,
          opacity: appear,
          transform: `translateY(${interpolate(appear, [0, 1], [24, 0])}px)`,
        }}
      >
        <p
          style={{
            margin: 0,
            color: '#EDEDEF',
            fontFamily: 'Inter, system-ui, sans-serif',
            fontWeight: 700,
            fontSize: 52,
            lineHeight: 1.25,
            textAlign: 'center',
            textWrap: 'balance',
          }}
        >
          {active.text}
        </p>
      </div>
    </AbsoluteFill>
  );
};
