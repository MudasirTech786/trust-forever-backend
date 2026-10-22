<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function publicTrending(): JsonResponse
    {
        $profiles = Profile::query()
            ->select([
                'id',
                'profile_id',
                'gender',
                'marital_status',
                'date_of_birth',
                'age',
                'height',
                'sect',
                'caste',
                'qualification',
                'educational_institution',
                'job',
                'business',
                'city',
                'created_at',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (Profile $profile): array => $this->publicProfileData($profile))
            ->values();

        return response()->json(['profiles' => $profiles]);
    }

    public function publicSearch(Request $request): JsonResponse
    {
        $requestedGender = $request->query('gender');
        if (is_string($requestedGender)) {
            $request->query->set('gender', strtolower(trim($requestedGender)));
        }

        $filters = $request->validate([
            'gender' => ['nullable', Rule::in(['male', 'female', 'all'])],
            'marital_status' => ['nullable', Rule::in(['unmarried', 'married', 'divorced', 'widowed'])],
            'education' => ['nullable', Rule::in(['matric', 'intermediate', 'bachelors', 'masters', 'mphil', 'phd', 'professional'])],
            'caste' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'sect' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(['doctors', 'engineers', 'other-educated', 'late-marriage', 'second-marriage', 'ahle-tashi'])],
            'min_age' => ['nullable', 'integer', 'min:18', 'max:100'],
            'max_age' => ['nullable', 'integer', 'min:18', 'max:100', 'gte:min_age'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Profile::query()
            ->select([
                'id', 'profile_id', 'gender', 'marital_status', 'date_of_birth', 'height',
                'sect', 'caste', 'qualification', 'educational_institution', 'job', 'business', 'city', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('profile_id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        foreach (['gender', 'marital_status', 'caste'] as $filter) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                if ($filter === 'gender' && $filters[$filter] === 'all') {
                    continue;
                }
                $query->where($filter, $filters[$filter]);
            }
        }

        if (! empty($filters['city'])) {
            $cities = array_values(array_filter(array_map('trim', explode(',', $filters['city']))));
            $query->whereIn('city', $cities);
        }

        if (! empty($filters['education'])) {
            $this->applyEducationFilter($query, $filters['education']);
        }

        if (! empty($filters['profession'])) {
            $this->applyProfessionFilter($query, $filters['profession']);
        }

        if (! empty($filters['sect'])) {
            $this->applySectFilter($query, $filters['sect']);
        }

        if (! empty($filters['category'])) {
            match ($filters['category']) {
                'doctors' => $query->where(function (Builder $categoryQuery): void {
                    $this->applyProfessionFilter($categoryQuery, 'Doctor');
                    $categoryQuery->orWhere('qualification', 'like', '%MBBS%')
                        ->orWhere('qualification', 'like', '%BDS%');
                }),
                'engineers' => $query->where(function (Builder $categoryQuery): void {
                    $this->applyProfessionFilter($categoryQuery, 'Engineer');
                    $categoryQuery->orWhere('qualification', 'like', '%engineering%');
                }),
                'other-educated' => $query->whereNotNull('qualification')->where('qualification', '<>', ''),
                'late-marriage' => $query->whereDate('date_of_birth', '<=', now()->subYears(35)->toDateString()),
                'second-marriage' => $query->whereIn('marital_status', ['divorced', 'widowed']),
                'ahle-tashi' => $this->applySectFilter($query, 'Ahle Tashi'),
            };
        }

        if (isset($filters['min_age'])) {
            $query->whereDate('date_of_birth', '<=', now()->subYears((int) $filters['min_age'])->toDateString());
        }

        if (isset($filters['max_age'])) {
            $query->whereDate('date_of_birth', '>', now()->subYears((int) $filters['max_age'] + 1)->toDateString());
        }

        $total = (clone $query)->count();
        $perPage = (int) ($filters['per_page'] ?? 50);
        $page = (int) ($filters['page'] ?? 1);
        $profiles = $query->forPage($page, $perPage)->get()
            ->map(fn (Profile $profile): array => $this->publicProfileData($profile))
            ->values();

        return response()->json([
            'profiles' => $profiles,
            'total' => $total,
            'limit' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function publicShow(string $profileId): JsonResponse
    {
        $profile = Profile::query()
            ->select([
                'profile_id', 'gender', 'marital_status', 'date_of_birth', 'height', 'sect', 'caste',
                'qualification', 'educational_institution', 'job', 'business', 'city',
            ])
            ->where('profile_id', $profileId)
            ->firstOrFail();

        return response()->json(['profile' => $this->publicProfileData($profile)]);
    }

    public function dashboardSummary(): JsonResponse
    {
        $counts = Profile::query()
            ->selectRaw("COUNT(*) AS total_profiles, SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male_profiles, SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female_profiles, SUM(CASE WHEN marital_status = 'unmarried' THEN 1 ELSE 0 END) AS unmarried_profiles, SUM(CASE WHEN marital_status = 'married' THEN 1 ELSE 0 END) AS married_profiles")
            ->first();

        $recentProfiles = Profile::query()
            ->select(['id', 'profile_id', 'profile_picture', 'name', 'gender', 'age', 'date_of_birth', 'city', 'qualification', 'caste', 'sect'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return response()->json([
            'statistics' => [
                'total_profiles' => (int) $counts->total_profiles,
                'male_profiles' => (int) $counts->male_profiles,
                'female_profiles' => (int) $counts->female_profiles,
                'unmarried_profiles' => (int) $counts->unmarried_profiles,
                'married_profiles' => (int) $counts->married_profiles,
            ],
            'recent_profiles' => $recentProfiles->map(fn (Profile $profile): array => [
                'id' => $profile->id,
                'profile_id' => $profile->profile_id,
                'profile_picture_url' => $profile->profile_picture
                    ? Storage::disk('public')->url($profile->profile_picture)
                    : Storage::disk('public')->url('matrimonial-profiles/avatar.jpg'),
                'name' => $profile->name,
                'gender' => $profile->gender,
                'age' => $profile->age ?? $profile->date_of_birth?->age,
                'city' => $profile->city,
                'qualification' => $profile->qualification,
                'caste' => $profile->caste,
                'sect' => $profile->sect,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Profile::query()->orderByDesc('created_at');
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('profile_id', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('caste', 'like', "%{$search}%")
                    ->orWhere('qualification', 'like', "%{$search}%");
            });
        }

        foreach (['gender', 'marital_status', 'city', 'sect', 'caste'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }

        if ($request->query('employed') !== null && $request->query('employed') !== '') {
            $query->where('employed', filter_var($request->query('employed'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['profiles' => ProfileResource::collection($query->get())->resolve()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatedData($request, true);
        $picturePath = null;
        if ($request->hasFile('profile_picture')) {
            $picturePath = $request->file('profile_picture')->storePublicly('matrimonial-profiles', 'public');
            if (! is_string($picturePath)) {
                throw ValidationException::withMessages(['profile_picture' => ['The profile picture could not be saved. Please try again.']]);
            }
        }

        unset($validated['remove_profile_picture']);
        $profile = DB::transaction(fn (): Profile => Profile::create([
            ...$validated,
            'profile_picture' => $picturePath,
            'created_by' => $request->user()->getKey(),
        ]));

        return response()->json(['profile' => (new ProfileResource($profile))->resolve()], 201);
    }

    public function show(Profile $profile): JsonResponse
    {
        return response()->json(['profile' => (new ProfileResource($profile))->resolve()]);
    }

    public function update(Request $request, Profile $profile): JsonResponse
    {
        $validated = $this->validatedData($request, false);
        $previousPicture = $profile->profile_picture;
        unset($validated['remove_profile_picture']);

        if ($request->hasFile('profile_picture')) {
            $picturePath = $request->file('profile_picture')->storePublicly('matrimonial-profiles', 'public');
            if (! is_string($picturePath)) {
                throw ValidationException::withMessages(['profile_picture' => ['The profile picture could not be saved. Please try again.']]);
            }
            $validated['profile_picture'] = $picturePath;
        } elseif ($request->boolean('remove_profile_picture')) {
            $validated['profile_picture'] = null;
        }

        $profile->fill($validated)->save();
        if ($previousPicture && $previousPicture !== $profile->profile_picture) {
            Storage::disk('public')->delete($previousPicture);
        }

        return response()->json(['profile' => (new ProfileResource($profile->refresh()))->resolve()]);
    }

    public function destroy(Profile $profile): Response
    {
        $picture = $profile->profile_picture;
        $profile->delete();
        if ($picture) {
            Storage::disk('public')->delete($picture);
        }

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function publicProfileData(Profile $profile): array
    {
        return [
            'profile_id' => $profile->profile_id,
            'gender' => $profile->gender,
            'marital_status' => $profile->marital_status,
            'age' => $profile->age ?? $profile->date_of_birth?->age,
            'height' => $profile->height,
            'sect' => $profile->sect,
            'caste' => $profile->caste,
            'qualification' => $profile->qualification,
            'educational_institution' => $profile->educational_institution,
            'job' => $profile->job,
            'business' => $profile->business,
            'city' => $profile->city,
        ];
    }

    private function applyEducationFilter(Builder $query, string $education): void
    {
        $patterns = match ($education) {
            'matric' => ['%matric%', '%SSC%', '%secondary%'],
            'intermediate' => ['%intermediate%', '%HSSC%', '%FSc%', '%F.A%', '%FA%', '%ICS%', '%I.Com%', '%F.Com%'],
            'bachelors' => ['%bachelor%', '%BS %', 'BS', '%BSc%', '%BA %', 'BA', '%B.Com%', '%BCom%', '%BE %', 'BE', '%B.E.%'],
            'masters' => ['%master%', '%MA %', 'MA', '%MSc%', '%MS %', 'MS', '%MBA%', '%M.Com%', '%MCom%'],
            'mphil' => ['%MPhil%', '%M.Phil%'],
            'phd' => ['%PhD%', '%Ph.D%', '%doctorate%'],
            'professional' => ['%MBBS%', '%BDS%', '%MD %', 'MD', '%FCPS%', '%PharmD%', '%ACCA%', '%CA %', 'CA', '%CFA%'],
            default => [],
        };

        $query->where(function (Builder $educationQuery) use ($patterns): void {
            foreach ($patterns as $pattern) {
                $educationQuery->orWhere('qualification', 'like', $pattern);
            }
        });
    }

    private function applyProfessionFilter(Builder $query, string $profession): void
    {
        $term = $this->normalizeSearchTerm($profession);
        if ($term === '') {
            return;
        }

        $terms = match ($term) {
            'doctor', 'dr', 'physician' => ['doctor', 'medical doctor', 'dr', 'physician'],
            'engineer', 'engineering' => ['engineer', 'engineering'],
            default => [$term],
        };

        $query->where(function (Builder $professionQuery) use ($terms): void {
            foreach (['job', 'business'] as $column) {
                $normalizedColumn = $this->normalizedColumnExpression($column);
                foreach ($terms as $term) {
                    $professionQuery->orWhere(function (Builder $termQuery) use ($normalizedColumn, $term): void {
                        $termQuery->whereRaw("{$normalizedColumn} = ?", [$term])
                            ->orWhereRaw("{$normalizedColumn} LIKE ?", [$term.' %'])
                            ->orWhereRaw("{$normalizedColumn} LIKE ?", ['% '.$term.' %'])
                            ->orWhereRaw("{$normalizedColumn} LIKE ?", ['% '.$term]);
                    });
                }
            }
        });
    }

    private function applySectFilter(Builder $query, string $sect): void
    {
        $term = $this->normalizeSearchTerm($sect);
        if ($term === '') {
            return;
        }

        $terms = match ($term) {
            'ahle tashi', 'ahle tashee', 'ahl e tashi', 'ahl e tashee' => [
                'ahle tashi', 'ahle tashee', 'ahl e tashi', 'ahl e tashee',
            ],
            'shia', 'shiya' => ['shia', 'shiya'],
            default => [$term],
        };

        $normalizedColumn = $this->normalizedColumnExpression('sect');
        $placeholders = implode(', ', array_fill(0, count($terms), '?'));
        $query->whereRaw("{$normalizedColumn} IN ({$placeholders})", $terms);
    }

    private function normalizeSearchTerm(string $value): string
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = preg_replace('/[^\\pL\\pN]+/u', ' ', $normalized) ?? '';
        return trim(preg_replace('/\\s+/u', ' ', $normalized) ?? '');
    }

    private function normalizedColumnExpression(string $column): string
    {
        if (! in_array($column, ['job', 'business', 'sect'], true)) {
            throw new \InvalidArgumentException('Unsupported profile search column.');
        }

        $expression = "LOWER({$column})";
        foreach ([".", ",", "-", "_", "/", "(", ")", ":", ";"] as $punctuation) {
            $expression = "REPLACE({$expression}, '{$punctuation}', ' ')";
        }

        for ($pass = 0; $pass < 3; $pass++) {
            $expression = "REPLACE({$expression}, '  ', ' ')";
        }

        return "TRIM({$expression})";
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, bool $creating): array
    {
        return $request->validate([
            'profile_picture' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_picture' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::in(['female', 'male'])],
            'marital_status' => ['sometimes', 'nullable', Rule::in(['unmarried', 'married', 'divorced', 'widowed'])],
            'age' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:120'],
            'date_of_birth' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today'],
            'height' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sect' => ['sometimes', 'nullable', 'string', 'max:255'],
            'caste' => ['sometimes', 'nullable', 'string', 'max:255'],
            'language' => ['sometimes', 'nullable', 'string', 'max:255'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:255'],
            'educational_institution' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employed' => ['sometimes', 'nullable', 'boolean'],
            'job' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business' => ['sometimes', 'nullable', 'string', 'max:255'],
            'income' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'father_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'father_occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mother_details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'other_details' => ['sometimes', 'nullable', 'string', 'max:12000'],
            'house_size' => ['sometimes', 'nullable', 'string', 'max:255'],
            'society' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sisters_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'sisters_details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'married_sisters_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'brothers_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'brothers_details' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'married_brothers_count' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'partner_requirements' => ['sometimes', 'nullable', 'string', 'max:12000'],
            'father_whatsapp' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
            'mother_whatsapp' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
            'parents_whatsapp' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\\+?[0-9 ()-]{7,30}$/'],
        ]);
    }
}
