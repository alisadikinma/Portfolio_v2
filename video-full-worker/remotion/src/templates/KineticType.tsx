import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig, interpolate, spring, z } from 'remotion';

export const kineticSchema = z.object({
  lines: z.array(z.string()),
  durationInSeconds: z.number(),
});

type Props = z.infer<typeof kineticSchema>;

/**
 * Explainer-recreate template: kinetic typography on the brand deep background —
 * each line springs in, staggered. Used to REBUILD an explanation b-roll in Ali's
 * own look (so it's not 100% like the source).
 */
export const KineticType: React.FC<Props> = ({ lines }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return (
    <AbsoluteFill style={{ background: '#050506', justifyContent: 'center', padding: '0 90px' }}>
      {lines.map((line, i) => {
        const enter = spring({ frame: frame - i * 8, fps, config: { damping: 200 } });
        return (
          <h1
            key={i}
            style={{
              margin: '0 0 18px',
              color: i === 0 ? '#D4A843' : '#EDEDEF',
              fontFamily: 'Space Grotesk, system-ui, sans-serif',
              fontWeight: 700,
              fontSize: 96,
              lineHeight: 1.05,
              opacity: enter,
              transform: `translateX(${interpolate(enter, [0, 1], [-60, 0])}px)`,
            }}
          >
            {line}
          </h1>
        );
      })}
    </AbsoluteFill>
  );
};
