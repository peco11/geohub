<?php

namespace App\Models;

use App\Providers\HoquServiceProvider;
use App\Traits\GeometryFeatureTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Class TaxonomyWhere
 *
 * @package App\Models
 *
 * @property string import_method
 * @property int    id
 */
class TaxonomyWhere extends Model {
    use HasFactory, GeometryFeatureTrait;

    protected $table = 'taxonomy_wheres';
    protected $fillable = [
        'name',
        'import_method'
    ];
    private HoquServiceProvider $hoquServiceProvider;

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
        $this->hoquServiceProvider = app(HoquServiceProvider::class);
    }

    /**
     * All the taxonomy where imported using a sync command are not editable
     *
     * @return bool
     */
    public function isEditableByUserInterface(): bool {
        return !$this->isImportedByExternalData();
    }

    /**
     * Check if the current taxonomy where is imported from an external source
     *
     * @return bool
     */
    public function isImportedByExternalData(): bool {
        return !is_null($this->import_method);
    }

    public function save(array $options = []) {
        parent::save($options);
        try {
            $this->hoquServiceProvider->store('update_geomixer_taxonomy_where', ['id' => $this->id]);
        } catch (\Exception $e) {
            Log::error('An error occurred during a store operation: ' . $e->getMessage());
        }
    }

    public function ugc_pois(): BelongsToMany {
        return $this->belongsToMany(UgcPoi::class);
    }

    public function ugc_tracks(): BelongsToMany {
        return $this->belongsToMany(UgcTrack::class);
    }

    public function ugc_media(): BelongsToMany {
        return $this->belongsToMany(UgcMedia::class);
    }
}
