import React from 'react';
import { AbsoluteFill, useCurrentFrame, useVideoConfig, spring, z } from 'remotion';

export const bulletSchema = z.object({
  title: z.string(),
  bullets: z.array(z.string()),
  durationInSeconds: z.number(),
});

type Props = z.infer<typeof bulletSchema>;

/**
 * Explainer-recreate template: a title + staggered bullet reveal on the brand
 * background — rebuilds a list/explanation b-roll in Ali's own look.
 */
export const BulletReveal: React.FC<Props> = ({ title, bullets }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return (
    <AbsoluteFill style={{ background: '#0C0C0F', justifyContent: 'center', padding: '0 100px' }}>
      <h1
        style={{
          color: '#EDEDEF', fontFamily: 'Space Grotesk, sans-serif', fontWeight: 700,
          fontSize: 84, margin: '0 0 56px',
        }}
      >
        {title}
      </h1>
      {bullets.map((b, i) => {
        const enter = spring({ frame: frame - 10 - i * 12, fps, config: { damping: 200 } });
        return (
          <div key={i} style={{ display: 'flex', alignItems: 'center', marginBottom: 34, opacity: enter }}>
            <span style={{ color: '#D4A843', fontSize: 56, marginRight: 28 }}>▸</span>
            <span style={{ color: '#EDEDEF', fontFamily: 'Inter, sans-serif', fontWeight: 500, fontSize: 60 }}>{b}</span>
          </div>
        );
      })}
    </AbsoluteFill>
  );
};
