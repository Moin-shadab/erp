<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModuleScannerService
{
    /**
     * Scan the resources/views/modules directory and dynamically register modules and pages.
     */
    public static function scan()
    {
        $modulesPath = resource_path('views/modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $directories = File::directories($modulesPath);

        foreach ($directories as $dir) {
            $folderName = basename($dir);

            // Ignore system or special folders if any
            if (in_array(strtolower($folderName), ['layouts', 'partials'])) {
                continue;
            }

            // Find all subdirectories in this module directory
            $subDirectories = File::directories($dir);

            // Filter subdirectories that contain a main.blade.php file
            $pageDirs = [];
            foreach ($subDirectories as $subDir) {
                if (File::exists($subDir . '/main.blade.php')) {
                    $pageDirs[] = $subDir;
                }
            }

            if (empty($pageDirs)) {
                continue;
            }

            // 1. Resolve or Create the Module
            $moduleId = null;

            // Try to find if any page belonging to this subdirectory already exists in pages table
            $existingPage = DB::table('pages')
                ->where(function ($query) use ($folderName) {
                    $query->where('custom_view', 'like', 'modules/' . $folderName . '/%')
                          ->orWhere('custom_view', 'like', 'modules.' . $folderName . '.%');
                })
                ->first();

            if ($existingPage) {
                $moduleId = $existingPage->module_id;
            } else {
                // Check if a module with corresponding Title Case name already exists
                $moduleName = Str::title(str_replace(['_', '-'], ' ', Str::snake($folderName, ' ')));
                $existingModule = DB::table('modules')
                    ->where('name', $moduleName)
                    ->first();

                if ($existingModule) {
                    $moduleId = $existingModule->id;
                } else {
                    // Create new module
                    $moduleId = DB::table('modules')->insertGetId([
                        'name' => $moduleName,
                        'icon' => 'bi-folder',
                        'sequence' => (DB::table('modules')->max('sequence') ?? 0) + 1,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 2. Scan and register each page subdirectory
            foreach ($pageDirs as $pageDir) {
                $pageFolderName = basename($pageDir);
                $customView = 'modules/' . $folderName . '/' . $pageFolderName;

                // Check if the page is already registered
                $pageExists = DB::table('pages')
                    ->where('custom_view', $customView)
                    ->exists();

                if (!$pageExists) {
                    // Generate unique slug
                    $baseSlug = Str::slug($folderName . '-' . $pageFolderName);
                    $slug = $baseSlug;
                    $counter = 1;
                    while (DB::table('pages')->where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '-' . $counter;
                        $counter++;
                    }

                    // Get human-friendly name
                    $pageName = Str::title(str_replace(['_', '-'], ' ', Str::snake($pageFolderName, ' ')));

                    // Get next fallback token
                    $token = self::getNextFallbackToken();

                    DB::table('pages')->insert([
                        'module_id' => $moduleId,
                        'name' => $pageName,
                        'slug' => $slug,
                        'token' => $token,
                        'title' => $pageName,
                        'db_table' => null,
                        'primary_key' => 'id',
                        'is_custom' => true,
                        'custom_view' => $customView,
                        'icon' => 'bi-file-earmark',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Calculate the next fallback numeric token.
     */
    public static function getNextFallbackToken()
    {
        $tokens = DB::table('pages')
            ->whereNotNull('token')
            ->pluck('token');

        $maxNumeric = 0;
        foreach ($tokens as $token) {
            if (is_numeric($token)) {
                $val = intval($token);
                if ($val > $maxNumeric) {
                    $maxNumeric = $val;
                }
            }
        }

        return (string) (max($maxNumeric, 1000) + 1);
    }
}
