<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GenerateIndexHtml extends Command
{
    protected $signature = 'meta:generate';
    protected $description = 'Generate index.html with meta tags from database settings';

    public function handle()
    {
        $this->info('🚀 Generating index.html from database settings...');

        // Fetch settings from database
        $siteSettings = DB::table('settings')->where('group', 'site')->get()->keyBy('key');
        $aboutSettings = DB::table('settings')->where('group', 'about')->get()->keyBy('key');

        $siteName = $siteSettings['site_name']->value ?? 'Portfolio';
        $siteDescription = $siteSettings['site_description']->value ?? 'Portfolio website';
        $siteLogo = $siteSettings['site_logo']->value ?? '';
        $profilePhoto = $aboutSettings['profile_photo']->value ?? '';
        $bioTitle = $aboutSettings['title']->value ?? '';
        
        // Use profile photo for OG image (more personal)
        $ogImage = $profilePhoto ?: $siteLogo;
        
        // Construct full URLs
        $appUrl = config('app.url');
        $ogImageUrl = $ogImage ? $appUrl . $ogImage : '';
        
        // Full title for SEO
        $fullTitle = $bioTitle ? "$siteName - $bioTitle" : $siteName;

        $this->info("📝 Site Name: $siteName");
        $this->info("📝 Full Title: $fullTitle");
        $this->info("📝 Description: " . substr($siteDescription, 0, 80) . "...");
        $this->info("📝 OG Image: $ogImageUrl");

        // Generate HTML content
        $html = $this->generateHtmlTemplate($fullTitle, $siteName, $siteDescription, $ogImageUrl, $appUrl);

        // Write to frontend source
        $frontendPath = base_path('../frontend/index.html');
        File::put($frontendPath, $html);
        $this->info("✅ Updated: $frontendPath");

        // Write to frontend dist if exists
        $distPath = base_path('../frontend/dist/index.html');
        if (File::exists(dirname($distPath))) {
            File::put($distPath, $html);
            $this->info("✅ Updated: $distPath");
        }

        $this->info('🎉 index.html generated successfully!');
        $this->warn('⚠️  Run "npm run build" in frontend to apply changes');

        return 0;
    }

    private function generateHtmlTemplate($title, $siteName, $description, $ogImage, $appUrl)
    {
        return <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <!-- Primary Meta Tags -->
    <title>$title</title>
    <meta name="title" content="$title">
    <meta name="description" content="$description">
    <meta name="author" content="$siteName">
    <meta name="keywords" content="Full-Stack Developer, Vue.js, Laravel, Web Development, Portfolio, Digital Solutions, AI Automation">
    
    <!-- Open Graph / Facebook / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="$appUrl/">
    <meta property="og:title" content="$title">
    <meta property="og:description" content="$description">
    <meta property="og:image" content="$ogImage">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="$siteName">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="$appUrl/">
    <meta name="twitter:title" content="$title">
    <meta name="twitter:description" content="$description">
    <meta name="twitter:image" content="$ogImage">
    
    <!-- Additional SEO -->
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    <link rel="canonical" href="$appUrl/">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    
    <!-- Theme Color for Mobile Browsers -->
    <meta name="theme-color" content="#ffffff">
    <meta name="msapplication-TileColor" content="#ffffff">
    
    <!-- CKEditor 5 CDN -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
    
    <!-- Font Awesome 6 CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
HTML;
    }
}
