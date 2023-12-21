<?php

namespace App\Models;

use App\Providers\HoquServiceProvider;
use App\Traits\GeometryFeatureTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Class UgcMedia
 *
 *
 * @property int    id
 * @property string app_id
 * @property string relative_url
 * @property string geometry
 * @property string name
 * @property string description
 * @property string raw_data
 */
class UgcMedia extends Model
{
    use GeometryFeatureTrait, HasFactory;

    protected $fillable = [
        'user_id',
        'app_id',
        'name',
        'description',
        'relative_url',
        'raw_data',
        'geometry',
    ];

    public $preventHoquSave = false;

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($media) {
            if (! $media->preventHoquSave) {
                try {
                    $hoquServiceProvider = app(HoquServiceProvider::class);
                    $hoquServiceProvider->store('update_ugc_media_position', ['id' => $media->id]);
                } catch (\Exception $e) {
                    Log::error('An error occurred during a store operation: '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Save the ugc media to the database without pushing any new HOQU job
     */
    public function saveWithoutHoquJob()
    {
        $this->preventHoquSave = true;
        $this->save();
        $this->preventHoquSave = false;
    }

    /**
     * Scope a query to only include current user EcMedia.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCurrentUser($query)
    {
        return $query->where('user_id', Auth()->user()->id);
    }

    public function ugc_pois(): BelongsToMany
    {
        return $this->belongsToMany(UgcPoi::class);
    }

    public function ugc_tracks(): BelongsToMany
    {
        return $this->belongsToMany(UgcTrack::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function taxonomy_wheres(): BelongsToMany
    {
        return $this->belongsToMany(TaxonomyWhere::class);
    }

    /**
     * Return the json version of the ugc media, avoiding the geometry
     * TODO: unit TEST
     */
    public function getJson(): array
    {
        $array = $this->toArray();

        $propertiesToClear = ['geometry'];
        foreach ($array as $property => $value) {
            if (is_null($value) || in_array($property, $propertiesToClear)) {
                unset($array[$property]);
            }

            if ($property == 'relative_url') {
                if (Storage::disk('public')->exists($value)) {
                    $array['url'] = Storage::disk('public')->url($value);
                }
                unset($array[$property]);
            }
        }

        return $array;
    }

    /**
     * Create a geojson from the ec track
     */
    public function getGeojson(): ?array
    {
        $feature = $this->getEmptyGeojson();
        if (isset($feature['properties'])) {
            $feature['properties'] = $this->getJson();

            return $feature;
        } else {
            return null;
        }
    }

    public function setGeometry(array $geometry)
    {
    }
}
