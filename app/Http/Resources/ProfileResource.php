<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'profile_picture_url' => $this->profile_picture
                ? Storage::disk('public')->url($this->profile_picture)
                : Storage::disk('public')->url('matrimonial-profiles/avatar.jpg'),
            'name' => $this->name,
            'gender' => $this->gender,
            'marital_status' => $this->marital_status,
            'age' => $this->age ?? $this->date_of_birth?->age,
            'height' => $this->height,
            'sect' => $this->sect,
            'caste' => $this->caste,
            'language' => $this->language,
            'qualification' => $this->qualification,
            'educational_institution' => $this->educational_institution,
            'employed' => $this->employed,
            'job' => $this->job,
            'business' => $this->business,
            'income' => $this->income,
            'father_details' => $this->father_details,
            'father_name' => $this->father_name,
            'father_occupation' => $this->father_occupation,
            'mother_details' => $this->mother_details,
            'other_details' => $this->other_details,
            'house_size' => $this->house_size,
            'society' => $this->society,
            'city' => $this->city,
            'sisters_count' => $this->sisters_count,
            'sisters_details' => $this->sisters_details,
            'married_sisters_count' => $this->married_sisters_count,
            'brothers_count' => $this->brothers_count,
            'brothers_details' => $this->brothers_details,
            'married_brothers_count' => $this->married_brothers_count,
            'partner_requirements' => $this->partner_requirements,
            'father_whatsapp' => $this->father_whatsapp,
            'mother_whatsapp' => $this->mother_whatsapp,
            'whatsapp_number' => $this->whatsapp_number,
            'parents_whatsapp' => $this->parents_whatsapp,
            'created_at' => $this->created_at,
        ];
    }
}
