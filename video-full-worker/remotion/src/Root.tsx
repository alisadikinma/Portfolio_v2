import React from 'react';
import { Composition } from 'remotion';
import { Captions, captionsSchema } from './Captions';
import { KineticType, kineticSchema } from './templates/KineticType';
import { BulletReveal, bulletSchema } from './templates/BulletReveal';

const FPS = 30;
const W = 1080;
const H = 1920;

export const RemotionRoot: React.FC = () => (
  <>
    <Composition
      id="Captions"
      component={Captions}
      schema={captionsSchema}
      fps={FPS}
      width={W}
      height={H}
      defaultProps={{ cues: [], durationInSeconds: 10 }}
      calculateMetadata={({ props }) => ({
        durationInFrames: Math.max(1, Math.round((props.durationInSeconds || 10) * FPS)),
      })}
    />
    <Composition
      id="KineticType"
      component={KineticType}
      schema={kineticSchema}
      fps={FPS}
      width={W}
      height={H}
      defaultProps={{ lines: ['Tools AI', 'yang hemat waktu'], durationInSeconds: 5 }}
      calculateMetadata={({ props }) => ({
        durationInFrames: Math.max(1, Math.round((props.durationInSeconds || 5) * FPS)),
      })}
    />
    <Composition
      id="BulletReveal"
      component={BulletReveal}
      schema={bulletSchema}
      fps={FPS}
      width={W}
      height={H}
      defaultProps={{ title: 'Kenapa penting', bullets: ['Cepat', 'Murah', 'Akurat'], durationInSeconds: 6 }}
      calculateMetadata={({ props }) => ({
        durationInFrames: Math.max(1, Math.round((props.durationInSeconds || 6) * FPS)),
      })}
    />
  </>
);
