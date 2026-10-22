# Trust Forever Marriage Bureau API

## Source and verification

This document describes the Laravel source and the output of `php artisan route:list` in this checkout. Laravel reports 39 registered routes: 33 application API route registrations and 6 framework, web, health, or Boost routes. API routes use the `/api` prefix. A `GET|HEAD` route accepts both methods. Profile and user updates accept both PUT and PATCH. Paths below are full route paths; prepend the deployment's API base URL (for example, local `http://localhost:8000`) to form a full URL. Application endpoints are listed below; framework routes appear under [Registered non-application routes](#registered-non-application-routes).

Response examples are representative source-derived structures, not captured live HTTP responses. The existing feature suite was run locally: 25 tests passed and 6 image-upload tests failed because the test filesystem could not create its fake public-disk directory. No live server or production database was used. Test assertions provide execution-level evidence for the tested authentication, user, role, permission, and validation behaviors; remaining response detail is inferred from controllers/resources. A validation failure normally returns HTTP 422 with `{"message":"...","errors":{"field":["..."]}}`; authentication failure is 401, permission denial is 403, missing bound records are 404, and other errors may follow Laravel's exception handler. These examples omit actual records and credentials.

Authentication uses Laravel's `web` session guard with Sanctum's stateful API middleware. Login establishes a session; send the session cookie and CSRF token as appropriate for a stateful browser client. Despite the Sanctum dependency and token table, these endpoints do not issue a bearer token. The OpenAPI bearer scheme is included only as an import-friendly placeholder and is **not** the implemented authentication mechanism. Use the cookie session in Postman. API authorization uses Spatie permissions on the `web` guard.

## Authentication

### `POST /api/login`

Public; throttled to 6 attempts per minute. Validates `email` (required email) and `password` (required string). Example body: `{"email":"operator@example.test","password":"<password>"}`. Success 200: `{"user":{"id":1,"name":"Example Operator","email":"operator@example.test","profile_picture_url":null}}`. Invalid credentials produce 422 validation JSON on `email`; field validation errors also return 422. Login regenerates the session.

### `GET /api/me`

Requires authenticated session (`auth:sanctum`). Returns the same safe `user` object as login; never returns password or remember token. Guest: 401.

### `POST /api/me`

Requires authenticated session. Multipart form is supported for image uploads. Optional `name` (string, max 255; if present cannot be empty) and `profile_picture` (image, JPG/JPEG/PNG/WebP, matching extension, max 2048 KB). Example multipart fields: `name=Example Operator`, `profile_picture=<file>`. Success 200: `{ "user": {"id":1,"name":"Example Operator","email":"operator@example.test","profile_picture_url":"/storage/profile-pictures/example.jpg"} }`. Picture is saved publicly under `profile-pictures`; replacing a picture deletes the prior file. Storage failure returns 500 with `{"message":"The profile picture could not be saved. Please try again."}`. There is no profile-picture removal field here.

### `POST /api/logout`

Requires authenticated session. Invalidates session and rotates CSRF token. Success 204 with an empty body. Guest: 401.

### `GET /api/me/permissions`

Requires authenticated session, no additional named permission middleware. 200: `{"roles":["manager"],"permissions":["profiles.view"]}`. Values are current role and effective permission names.

## Profiles

All profile endpoints require an authenticated session plus the stated permission. A profile response is wrapped as `{ "profile": { ... } }`; list is `{ "profiles": [...] }`. The resource contains `id`, generated `profile_id` (`TFMB-<id>`), `profile_picture_url`, optional `name`, `gender`, `marital_status`, `age`, `height`, `sect`, `caste`, `language`, `qualification`, `educational_institution`, `employed`, `job`, `business`, `income`, `father_details`, `father_name`, `father_occupation`, `mother_details`, `other_details`, `house_size`, `society`, `city`, `sisters_count`, `sisters_details`, `married_sisters_count`, `brothers_count`, `brothers_details`, `married_brothers_count`, `partner_requirements`, `father_whatsapp`, `mother_whatsapp`, `whatsapp_number`, `parents_whatsapp`, and `created_at`. A missing image URL resolves to the public-disk URL for `matrimonial-profiles/avatar.jpg`.

All fields are optional on create and update. Supplied values retain their validation: age is an integer 0–120; `gender` and `marital_status` use their listed enums; numeric sibling counts are 0–255; and contact strings must match `^\+?[0-9 ()-]{7,30}$`. Legacy DOB is accepted only for backward compatibility and is not returned; old DOB-only records continue to receive a calculated age. `profile_picture` is an optional image JPG/JPEG/PNG/WebP max 2048 KB. `remove_profile_picture` is optional boolean (used by update). Multipart is recommended when uploading a file.

### `GET /api/profiles` — `profiles.view`

Returns all matching profiles newest-created first; there is no pagination. Optional query filters: `search` (trimmed substring over name, profile_id, city, caste, qualification), exact `gender`, `marital_status`, `city`, `sect`, `caste`, and `employed` parsed as boolean. No query validation or sorting parameter exists. Success 200: `{ "profiles": [<ProfileResource>, ...] }`.

### `POST /api/profiles` — `profiles.create`

Create profile using the fields above. Example JSON: `{"name":"Sample Profile","age":28,"city":"Lahore"}`. Success 201: `{ "profile": <ProfileResource> }`. Creator is set from the authenticated user; profile ID is generated by model events.

### `GET /api/profiles/{profile}` — `profiles.view`

`profile` is the numeric profile primary key used for implicit model binding. Success 200: `{ "profile": <ProfileResource> }`; missing record 404.

### `PUT|PATCH /api/profiles/{profile}` — `profiles.edit`

Accepts optional update fields as above. Example JSON: `{"city":"Karachi"}`. Set `remove_profile_picture=true` to clear the picture; a supplied new image takes precedence. Success 200: `{ "profile": <ProfileResource> }`. Old image is deleted after replacement/removal.

### `DELETE /api/profiles/{profile}` — `profiles.delete`

Deletes profile and stored image. Success 204 empty body; missing record 404.

### `GET /api/dashboard/profile-summary` — `profiles.view`

Returns `statistics` (`total_profiles`, `male_profiles`, `female_profiles`, `unmarried_profiles`, `married_profiles`, all integers) and up to six newest `recent_profiles`. Each recent item has `id`, `profile_id`, `profile_picture_url`, `name`, `gender`, `age`, `city`, `qualification`, `caste`, `sect`. Counts cover only the categories named in code. No pagination.

## Users

User resource output is `{id,name,email,roles,created_at,updated_at}`. Credentials and profile image path are omitted. Routes have `auth:sanctum` and permission middleware, except role options which has a controller-level check. `role_id` usage additionally requires `users.roles.assign`.

### `GET /api/users` — `user.view`

Optional `search` matches name or email. Results are name-ascending, unpaginated. 200: `{ "users": [<UserResource>, ...] }`.

### `POST /api/users` — `user.create`

Requires `name` string max 255, `email` email max 255 unique, `password` confirmed and at least 8 chars including letters and numbers; optional nullable integer `role_id` existing on `web` guard (requires `users.roles.assign`). Send `password_confirmation` matching password. Example uses placeholders: `{"name":"New Staff","email":"staff@example.test","password":"<password>","password_confirmation":"<password>"}`. Success 201 `{ "user": <UserResource> }`.

### `GET /api/users/{user}` — `user.view`

Numeric user ID. 200 `{ "user": <UserResource> }`; absent user 404.

### `PUT|PATCH /api/users/{user}` — `user.update`

Requires `name` string max 255 and `email` email max 255 unique except current user; optional nullable integer `role_id` existing web role and requires `users.roles.assign`. Example `{"name":"Updated Staff","email":"staff@example.test"}`. Success 200 `{ "user": <UserResource> }`. Implementation requires name/email even for PATCH. Primary administrator email cannot be changed; role changes for the current user and protected primary administrator are blocked when changing to a different role.

### `PUT /api/users/{user}/password` — `user.update`

Body requires confirmed password, min 8 with letters and numbers, and `password_confirmation`. Success 204. No password is returned.

### `DELETE /api/users/{user}` — `user.delete`

Success 204. Deletion is rejected with 422 if deleting the current account, protected primary administrator, or last account with role name `admin`.

### `GET /api/user-management/roles`

Authenticated, controller allows `user.create` or `user.update` (singular permission names). Returns sorted web-guard role options: `{ "roles": [{"id":1,"name":"manager"}] }`. 403 if neither permission is present.

## Roles

Role representation: `{id,name,guard_name,permissions:[{id,name}]}`. Web guard roles only; sorted by name on list. All routes require the listed permission.

- `GET /api/roles` — `roles.view`: 200 `{ "roles": [<Role>, ...] }`.
- `POST /api/roles` — `roles.create`: body `name` required string max 255, unique among web roles. 201 `{ "role": <Role> }` (initial permissions empty).
- `GET /api/roles/{role}` — `roles.view`: numeric ID, 200 `{ "role": <Role> }`.
- `PUT /api/roles/{role}` — `roles.edit`: body requires `name` string max 255 unique among web roles except this role. 200 `{ "role": <Role> }`.
- `DELETE /api/roles/{role}` — `roles.delete`: 204; role named `admin` cannot be deleted (422). Other guard roles bind but are returned as 404.
- `PUT /api/roles/{role}/permissions` — `roles.edit`: body `permissions` is required array; each element integer, distinct, and an existing web permission ID. Empty array clears permissions. 200 `{ "role": <Role> }`.

## Permissions

All routes require the listed permission and operate on web guard permissions. Representation `{id,name,guard_name}`.

- `GET /api/permissions` — `permissions.view`: 200 `{ "permissions": [<Permission>, ...] }`, name ascending.
- `POST /api/permissions` — `permissions.create`: `name` required string max 255 unique for web guard. 201 `{ "permission": <Permission> }`.
- `PUT /api/permissions/{permission}` — `permissions.edit`: same name validation, unique except this ID. 200 `{ "permission": <Permission> }`.
- `DELETE /api/permissions/{permission}` — `permissions.delete`: 204; non-web guard IDs return 404.

## User roles and permissions

All endpoints require session auth plus the indicated permission. `roles` bodies use role **names**, unlike the user create/update `role_id`.

- `GET /api/users/{user}/roles` — `users.roles.view`: 200 `{ "roles": ["manager"] }`.
- `POST /api/users/{user}/roles` — `users.roles.assign`: body `roles` required array of distinct strings, each an existing web role name. Adds listed roles. 200 same shape as GET.
- `PUT /api/users/{user}/roles` — `users.roles.assign`: same body; replaces all roles. An empty array clears them. 200 same shape.
- `DELETE /api/users/{user}/roles` — `users.roles.remove`: same body; removes listed roles. 200 same shape.
- `GET /api/users/{user}/permissions` — `users.roles.view`: returns `{ "roles": ["manager"], "permissions": ["profiles.view"] }` with effective permission names.

## Errors and behavior notes

- Standard validation failures use status 422 and Laravel's `message` plus field-keyed `errors` object. The exact messages vary by failed rule.
- 401 indicates missing/invalid session; 403 indicates failed `can:` middleware or controller permission check; 404 includes missing resources and deliberately hidden non-web role/permission records.
- No application list endpoint is paginated. Only profile/user search and profile exact filters above are implemented; no caller-controlled sort exists.
- Profile and user uploads require multipart form-data. Storage URLs depend on Laravel's public disk configuration and deployment URL; no credentials or actual personal records are used in examples.
- Validation is implemented inline in controllers; no dedicated Form Request classes or API Resource for user/role/permission error handling were found. Authorization is through `auth:sanctum`, route `can:` middleware, and the controller-level role-option checks described above; no application policy classes were found.
- Route-list review found no duplicate application method/path registrations. Response envelopes vary by resource (`user`, `profile`, plural list keys, and role/permission arrays); examples preserve those formats. Profile and user list query parameters are not validated, all lists are unpaginated, and only profile/user search plus profile exact filters are implemented.
- `GET|HEAD /` serves the Laravel welcome view, `/up` is the Laravel health route, Sanctum supplies `/sanctum/csrf-cookie`, public storage supplies `/storage/{path}` GET/HEAD and PUT, and Laravel Boost registers `POST /_boost/browser-logs`. They are registered but are not application JSON API endpoints. Their implementation is package/framework-owned and is not expanded into application request/response contracts here.

## Registered non-application routes

| Method | Path | Handler / purpose |
|---|---|---|
| GET, HEAD | `/` | `routes/web.php`; welcome view |
| POST | `/_boost/browser-logs` | Laravel Boost browser logs |
| GET, HEAD | `/sanctum/csrf-cookie` | Sanctum CSRF cookie endpoint |
| GET, HEAD | `/storage/{path}` | Laravel public storage serving |
| PUT | `/storage/{path}` | Laravel local storage upload route |
| GET, HEAD | `/up` | Laravel health check |
