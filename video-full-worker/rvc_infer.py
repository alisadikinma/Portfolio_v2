#!/usr/bin/env python3
"""
RVC voice-change adapter for video-full-worker.

voice.js (changeVoice → runRvc) invokes:
    <RVC_PYTHON> rvc_infer.py --input X --output Y --model Z [--index W]

This maps those flags onto the rvc-python RVCInference API and runs the
speech-to-speech conversion on Apple-Silicon MPS with the rmvpe pitch
extractor. It is a standalone CLI subprocess (the worker never links
rvc-python's code) — the same arm's-length pattern the worker already uses
for ffmpeg/yt-dlp. Timing is preserved (RVC only swaps timbre), so Veo's
lip-sync stays valid and every clip ends up in one consistent Ali voice.

Phase 1 (training, one-time) produces the .pth/.index passed as --model/--index.
"""
import argparse
import warnings

warnings.filterwarnings("ignore")


def main():
    ap = argparse.ArgumentParser(description="RVC voice-change adapter (video-full-worker)")
    ap.add_argument("--input", required=True, help="source wav (e.g. the Veo clip audio)")
    ap.add_argument("--output", required=True, help="output wav in the trained voice")
    ap.add_argument("--model", required=True, help="trained RVC model (.pth)")
    ap.add_argument("--index", default="", help="optional .index for timbre retrieval")
    ap.add_argument("--device", default="mps:0", help="mps:0 (Apple Silicon) / cuda:0 / cpu:0")
    ap.add_argument("--method", default="rmvpe", choices=["harvest", "crepe", "rmvpe", "pm"])
    ap.add_argument("--index-rate", type=float, default=0.66)
    ap.add_argument("--protect", type=float, default=0.33, help="protect breaths/consonants")
    ap.add_argument("--pitch", type=int, default=0, help="semitone transpose (0 = keep)")
    a = ap.parse_args()

    from rvc_python.infer import RVCInference

    rvc = RVCInference(device=a.device, model_path=a.model, index_path=a.index or "")
    rvc.set_params(
        f0method=a.method,
        index_rate=a.index_rate,
        protect=a.protect,
        f0up_key=a.pitch,
    )
    rvc.infer_file(a.input, a.output)
    print(f"[rvc_infer] {a.input} -> {a.output} (device={a.device}, method={a.method})")


if __name__ == "__main__":
    main()
