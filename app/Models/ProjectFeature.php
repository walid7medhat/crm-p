<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectFeature extends Pivot
{
    protected $table = 'project_features';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'feature_id',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the project that owns the ProjectFeature.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the feature that owns the ProjectFeature.
     */
    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }

    /**
     * Additional attributes to include in the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'feature_name',
        'project_title'
    ];

    /**
     * Get the feature name.
     */
    public function getFeatureNameAttribute()
    {
        return $this->feature ? $this->feature->name : null;
    }

    /**
     * Get the project title.
     */
    public function getProjectTitleAttribute()
    {
        return $this->project ? $this->project->title : null;
    }

    /**
     * Get the feature icon.
     */
    public function getFeatureIconAttribute()
    {
        return $this->feature && $this->feature->img 
            ? asset('storage/' . $this->feature->img)
            : null;
    }

    /**
     * Scope a query to only include specific project features.
     */
    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope a query to only include specific feature projects.
     */
    public function scopeForFeature($query, $featureId)
    {
        return $query->where('feature_id', $featureId);
    }

    /**
     * Attach multiple features to a project.
     *
     * @param Project $project
     * @param array $featureIds
     * @return void
     */
    public static function attachFeaturesToProject(Project $project, array $featureIds)
    {
        foreach ($featureIds as $featureId) {
            self::firstOrCreate([
                'project_id' => $project->id,
                'feature_id' => $featureId,
            ]);
        }
    }

    /**
     * Detach multiple features from a project.
     *
     * @param Project $project
     * @param array $featureIds
     * @return void
     */
    public static function detachFeaturesFromProject(Project $project, array $featureIds)
    {
        self::where('project_id', $project->id)
            ->whereIn('feature_id', $featureIds)
            ->delete();
    }

    /**
     * Get all features for a project with details.
     *
     * @param int $projectId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getFeaturesForProject(int $projectId)
    {
        return self::with('feature')
            ->where('project_id', $projectId)
            ->get()
            ->map(function ($projectFeature) {
                return [
                    'id' => $projectFeature->feature_id,
                    'name' => $projectFeature->feature_name,
                    'icon' => $projectFeature->feature_icon,
                    'attached_at' => $projectFeature->created_at,
                ];
            });
    }

    /**
     * Get all projects for a feature with details.
     *
     * @param int $featureId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getProjectsForFeature(int $featureId)
    {
        return self::with('project')
            ->where('feature_id', $featureId)
            ->get()
            ->map(function ($projectFeature) {
                return [
                    'id' => $projectFeature->project_id,
                    'title' => $projectFeature->project_title,
                    'attached_at' => $projectFeature->created_at,
                ];
            });
    }

    /**
     * Check if a feature is attached to a project.
     *
     * @param int $projectId
     * @param int $featureId
     * @return bool
     */
    public static function isFeatureAttached(int $projectId, int $featureId): bool
    {
        return self::where('project_id', $projectId)
            ->where('feature_id', $featureId)
            ->exists();
    }

    /**
     * Count how many projects have a specific feature.
     *
     * @param int $featureId
     * @return int
     */
    public static function countProjectsWithFeature(int $featureId): int
    {
        return self::where('feature_id', $featureId)->count();
    }

    /**
     * Count how many features a project has.
     *
     * @param int $projectId
     * @return int
     */
    public static function countFeaturesForProject(int $projectId): int
    {
        return self::where('project_id', $projectId)->count();
    }

    /**
     * Get statistics about project features.
     *
     * @return array
     */
    public static function getStatistics(): array
    {
        return [
            'total_connections' => self::count(),
            'most_used_features' => self::selectRaw('feature_id, COUNT(*) as count')
                ->with('feature')
                ->groupBy('feature_id')
                ->orderBy('count', 'DESC')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'feature_id' => $item->feature_id,
                        'feature_name' => $item->feature->name ?? 'Unknown',
                        'count' => $item->count,
                    ];
                }),
            'projects_with_most_features' => self::selectRaw('project_id, COUNT(*) as count')
                ->with('project')
                ->groupBy('project_id')
                ->orderBy('count', 'DESC')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'project_id' => $item->project_id,
                        'project_title' => $item->project->title ?? 'Unknown',
                        'count' => $item->count,
                    ];
                }),
        ];
    }
}