<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Project;
use App\Models\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(): JsonResponse
    {
        try {
            // Get counts
            $stats = [
                'projects' => [
                    'total' => Project::count(),
                    'published' => Project::count(), // No published filter for now
                    'draft' => 0, // No draft filter for now
                    'change' => $this->calculateChange('projects'),
                ],
                'posts' => [
                    'total' => Post::count(),
                    'published' => Post::count(), // No published filter for now
                    'draft' => 0, // No draft filter for now
                    'change' => $this->calculateChange('posts'),
                ],
                'gallery' => [
                    'total' => Gallery::count(),
                    'change' => $this->calculateChange('gallery'),
                ],
                'views' => [
                    'total' => $this->getTotalViews(),
                    'change' => $this->calculateViewsChange(),
                ],
            ];

            // Recent projects (5 latest)
            $recentProjects = Project::select('id', 'title', 'slug', 'created_at')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'title' => $project->title,
                        'slug' => $project->slug,
                        'status' => 'Published', // Default for now
                        'statusVariant' => 'success',
                        'date' => $project->created_at->format('M d, Y'),
                    ];
                });

            // Recent posts (5 latest)
            $recentPosts = Post::select('id', 'title', 'slug', 'views', 'created_at')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'title' => $post->title,
                        'slug' => $post->slug,
                        'status' => 'Published', // Default for now
                        'views' => $this->formatNumber($post->views ?? 0),
                        'date' => $post->created_at->format('M d, Y'),
                    ];
                });

            // Recent activity (last 10 activities)
            $recentActivity = $this->getRecentActivity();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recentProjects' => $recentProjects,
                    'recentPosts' => $recentPosts,
                    'recentActivity' => $recentActivity,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DASHBOARD_ERROR',
                    'message' => 'Failed to fetch dashboard data: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Calculate percentage change compared to last month
     */
    private function calculateChange(string $model): array
    {
        $modelClass = match ($model) {
            'projects' => Project::class,
            'posts' => Post::class,
            'gallery' => Gallery::class,
            default => null,
        };

        if (!$modelClass) {
            return ['percent' => 0, 'trend' => 'neutral'];
        }

        $thisMonth = $modelClass::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $lastMonth = $modelClass::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        if ($lastMonth === 0) {
            $percent = $thisMonth > 0 ? 100 : 0;
        } else {
            $percent = round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
        }

        return [
            'percent' => abs($percent),
            'trend' => $percent >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Get total views from posts
     */
    private function getTotalViews(): string
    {
        $postViews = Post::sum('views') ?? 0;
        // Note: Projects table doesn't have views column yet
        $total = $postViews;

        return $this->formatNumber($total);
    }

    /**
     * Calculate views change compared to last month
     */
    private function calculateViewsChange(): array
    {
        $thisMonthPosts = Post::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('views') ?? 0;

        // Note: Projects table doesn't have views column yet
        $thisMonth = $thisMonthPosts;

        $lastMonthPosts = Post::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('views') ?? 0;

        // Note: Projects table doesn't have views column yet
        $lastMonth = $lastMonthPosts;

        if ($lastMonth === 0) {
            $percent = $thisMonth > 0 ? 100 : 0;
        } else {
            $percent = round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1);
        }

        return [
            'percent' => abs($percent),
            'trend' => $percent >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * Get recent activity (latest created/updated items)
     */
    private function getRecentActivity(): array
    {
        $activities = [];

        // Recent projects
        $recentProjects = Project::select('id', 'title', 'created_at', 'updated_at')
            ->latest('updated_at')
            ->take(3)
            ->get();

        foreach ($recentProjects as $project) {
            $isNew = $project->created_at->eq($project->updated_at);
            $activities[] = [
                'id' => 'project_' . $project->id,
                'type' => 'project',
                'title' => $isNew ? 'New project created' : 'Project updated',
                'description' => $project->title . ' has been ' . ($isNew ? 'created' : 'updated'),
                'time' => $project->updated_at->diffForHumans(),
                'timestamp' => $project->updated_at->timestamp,
            ];
        }

        // Recent posts
        $recentPosts = Post::select('id', 'title', 'created_at', 'updated_at')
            ->latest('updated_at')
            ->take(3)
            ->get();

        foreach ($recentPosts as $post) {
            $isNew = $post->created_at->eq($post->updated_at);
            $activities[] = [
                'id' => 'post_' . $post->id,
                'type' => 'post',
                'title' => $isNew ? 'New blog post published' : 'Blog post updated',
                'description' => $post->title . ' has been ' . ($isNew ? 'published' : 'updated'),
                'time' => $post->updated_at->diffForHumans(),
                'timestamp' => $post->updated_at->timestamp,
            ];
        }

        // Recent gallery items
        $recentGallery = Gallery::select('id', 'title', 'created_at')
            ->latest()
            ->take(2)
            ->get();

        foreach ($recentGallery as $item) {
            $activities[] = [
                'id' => 'gallery_' . $item->id,
                'type' => 'gallery',
                'title' => 'Gallery item added',
                'description' => $item->title . ' added to gallery',
                'time' => $item->created_at->diffForHumans(),
                'timestamp' => $item->created_at->timestamp,
            ];
        }

        // Sort by timestamp (most recent first) and take 10
        usort($activities, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Format large numbers (e.g., 1200 => "1.2k")
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        }
        if ($number >= 1000) {
            return round($number / 1000, 1) . 'k';
        }
        return (string) $number;
    }

    /**
     * Get badge variant based on status
     */
    private function getStatusVariant(string $status): string
    {
        return match ($status) {
            'published' => 'success',
            'draft' => 'warning',
            'archived' => 'neutral',
            default => 'info',
        };
    }
}
