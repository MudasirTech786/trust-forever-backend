<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'profile_picture', 'name', 'gender', 'marital_status', 'date_of_birth', 'height',
        'sect', 'caste', 'language', 'qualification', 'educational_institution', 'employed', 'job', 'business', 'income',
        'father_details', 'father_name', 'father_occupation', 'mother_details', 'other_details', 'house_size', 'society', 'city', 'sisters_count',
        'sisters_details', 'married_sisters_count', 'brothers_count', 'brothers_details',
        'married_brothers_count', 'partner_requirements', 'father_whatsapp', 'mother_whatsapp',
        'whatsapp_number', 'parents_whatsapp', 'created_by',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date:Y-m-d', 'employed' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Profile $profile): void {
            $profile->setAttribute('profile_id', 'PENDING-'.bin2hex(random_bytes(16)));
        });

        static::created(function (Profile $profile): void {
            $profile->forceFill(['profile_id' => 'TFMB-'.$profile->getKey()])->saveQuietly();
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
