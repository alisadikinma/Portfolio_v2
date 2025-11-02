# Task: Batch Convert 56 PNG to Multi-Resolution WebP Images

## Objective
Convert Canva PNG exports to responsive WebP images for web deployment.

## Input
Location: `C:\xampp\htdocs\Portfolio_v2\.claude\raw_data\`
Files: 56 PNG files with format `{id}_{slug}.png`
Example: `2_ai-gift-box-counting.png`

## Output Requirements

### File Generation
For each PNG, generate 4 files:
```
Output: C:\xampp\htdocs\Portfolio_v2\frontend\public\storage\projects\

Example input: 2_ai-gift-box-counting.png
Output files:
├── ai-gift-box-counting-1200.webp  (200-220KB, desktop ≥1024px)
├── ai-gift-box-counting-900.webp   (130-150KB, tablet 768-1023px)
├── ai-gift-box-counting-600.webp   (80-100KB, mobile ≤767px)
└── ai-gift-box-counting-1200.jpg   (350-400KB, fallback)
```

### Naming Convention
- Parse filename: `{id}_{slug}.png` → extract slug only (remove ID prefix)
- Output uses slug: `{slug}-{size}.{format}`
- Handle slugs with underscores (rejoin all parts after first underscore)

## Technical Specs

### Image Processing
- **NO Cropping** - preserve full design
- **Maintain aspect ratio** - resize by width only
- **Sizes:** 600px, 900px, 1200px width
- **WebP:** Quality 85%, effort 6
- **JPEG:** Quality 80%, progressive encoding

### Dependencies
```json
{
  "dependencies": {
    "sharp": "^0.33.0",
    "fs-extra": "^11.0.0"
  }
}
```

## Script Structure
```
.claude/scripts/image-processor/
├── package.json
├── process-images.js       # Main script
├── config.js               # Settings
└── README.md              # Documentation
```

## Configuration
```javascript
// config.js
module.exports = {
  inputDir: 'C:\\xampp\\htdocs\\Portfolio_v2\\.claude\\raw_data',
  outputDir: 'C:\\xampp\\htdocs\\Portfolio_v2\\frontend\\public\\storage\\projects',
  sizes: [
    { width: 1200, suffix: '1200', formats: ['webp', 'jpg'] },
    { width: 900, suffix: '900', formats: ['webp'] },
    { width: 600, suffix: '600', formats: ['webp'] }
  ],
  quality: {
    webp: 85,
    jpeg: 80
  }
};
```

## Expected Output

### Console
```
🚀 Processing 56 PNG files...

[1/56] 1_production-ai-powered-metal-walk-through-monitoring-cctv.png (1.2MB)
  ✓ production-ai-powered-metal-walk-through-monitoring-cctv-1200.webp (215KB)
  ✓ production-ai-powered-metal-walk-through-monitoring-cctv-900.webp (142KB)
  ✓ production-ai-powered-metal-walk-through-monitoring-cctv-600.webp (88KB)
  ✓ production-ai-powered-metal-walk-through-monitoring-cctv-1200.jpg (385KB)
  Size reduction: 1.2MB → 830KB (31% smaller)

[2/56] 2_ai-gift-box-counting.png (980KB)
  ✓ ai-gift-box-counting-1200.webp (198KB)
  ✓ ai-gift-box-counting-900.webp (132KB)
  ✓ ai-gift-box-counting-600.webp (85KB)
  ✓ ai-gift-box-counting-1200.jpg (365KB)
  Size reduction: 980KB → 780KB (20% smaller)

...

✅ Batch processing complete!

Summary:
- Files processed: 56/56 (100%)
- Output files: 224 (56 × 4)
- Total input size: 67.2MB
- Total output size: 41.8MB
- Average reduction: 38%
- Processing time: 45 minutes

Output location: C:\xampp\htdocs\Portfolio_v2\frontend\public\storage\projects\
```

### Files Generated
Total: 224 files (56 projects × 4 versions)
```
frontend/public/storage/projects/
├── ai-gift-box-counting-1200.webp
├── ai-gift-box-counting-900.webp
├── ai-gift-box-counting-600.webp
├── ai-gift-box-counting-1200.jpg
├── ai-keyboard-sticker-notebook-inspection-1200.webp
├── ... (220 more files)
```

## Success Criteria
- ✅ All 56 PNG files processed successfully
- ✅ 224 output files generated (4 per project)
- ✅ Desktop: 180-250KB, Tablet: 120-160KB, Mobile: 70-110KB
- ✅ No cropping, full design preserved
- ✅ Processing completes in <60 minutes
- ✅ Error handling for corrupt files

## Error Handling
- Skip corrupt/unreadable PNG files
- Log errors to `errors.log`
- Continue processing remaining files
- Validate output file existence and size
- Generate summary report

## Usage
```bash
cd .claude/scripts/image-processor
npm install
node process-images.js
```

## Notes
- Sequential processing to avoid memory issues
- Progress bar with file counter and time estimate
- Output folder auto-created if doesn't exist
- Existing files overwritten without warning
- Windows path compatibility ensured