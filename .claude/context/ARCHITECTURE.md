# ARCHITECTURE.md — devio Standard Architecture (Laravel 12 + Inertia 2 + React 19)

> Read after RULES.md. This file defines WHERE code lives and HOW layers communicate.
> Default architecture is **Laravel monolith + Inertia**. See §9 for when to switch to API + SPA.

---

## 1. Request lifecycle (the only allowed path)

```
HTTP Request
  → routes/*.php            (route + middleware stack, named, grouped)
  → Middleware              (auth, verified, throttle, roles)
  → FormRequest             (authorize() + rules() — ALL input validation)
  → Controller              (≤ 7 RESTful methods or __invoke; orchestrates only)
  → Action / Service        (business logic, DB::transaction, events)
  → Models                  (relations, scopes, casts, accessors — no logic)
  → API Resource            (output shaping — the ONLY thing the frontend sees)
  → Inertia::render / JSON
```

Data flows down through typed parameters/DTOs, results flow up through return values. Layers never skip (controller never touches DB directly; React never receives a raw model).

## 2. Directory layout

```
app/
├── Actions/{Domain}/          # One business operation per class. handle() entry point.
│   └── Booking/CreateBooking.php
├── Services/                  # Multi-step/reusable domain logic; stateless; DI only.
│   └── SlotGenerator.php
├── DTOs/                      # readonly value objects crossing layer boundaries.
│   └── BookingData.php        #   public static fromRequest(StoreBookingRequest $r): self
├── Enums/                     # Backed string enums for every status/type + label()/color() helpers.
├── Events/  Listeners/  Jobs/ Notifications/
├── Exceptions/                # Domain exceptions: SlotAlreadyTakenException etc.
├── Http/
│   ├── Controllers/           # Public + Admin/ namespaces
│   ├── Middleware/
│   ├── Requests/{Domain}/     # Store*/Update* FormRequests
│   └── Resources/             # {Model}Resource + slim {Model}CardResource for lists
├── Models/                    # Explicit $fillable, casts(), relations, scopes ONLY
├── Policies/                  # One per model with mutations
└── Providers/

resources/js/
├── components/ui/             # shadcn primitives (don't edit; wrap instead)
├── components/devio/          # shared brand kit — reused across projects
├── components/{project}/      # project-specific composites
├── hooks/                     # useFilters, useCart… (logic lives here, not pages)
├── layouts/                   # PublicLayout, AdminLayout, AuthLayout
├── pages/                     # Inertia pages: props in → UI out. No fetching, no business logic.
├── lib/                       # utils, formatters (money, dates — Intl, UAE locale aware)
└── types/                     # interfaces mirroring API Resources, single source: types/models.ts
```

## 3. Layer contracts (what each may/may not do)

### Controller
```php
final class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, CreateBooking $action): RedirectResponse
    {
        $booking = $action->handle(BookingData::fromRequest($request));

        return to_route('bookings.show', $booking)
            ->with('success', __('Booking confirmed.'));
    }
}
```
May: resolve Actions via method injection, call one Action, return response. May not: validate, query, branch on business rules, touch DB.

### FormRequest
- `authorize()` does policy/ownership pre-checks (`$this->user()->can('create', Booking::class)`).
- `rules()` is exhaustive: types, max lengths, exists/unique with proper scoping, enum rules (`Rule::enum(BookingStatus::class)`).
- Normalization in `prepareForValidation()` (trim, phone formats).

### Action
```php
final readonly class CreateBooking
{
    public function __construct(
        private SlotGenerator $slots,
        private DatabaseManager $db,
    ) {}

    public function handle(BookingData $data): Booking
    {
        return $this->db->transaction(function () use ($data) {
            $this->slots->assertAvailable($data->doctorId, $data->startsAt);

            try {
                $booking = Booking::create($data->toArray());
            } catch (UniqueConstraintViolationException) {
                throw new SlotAlreadyTakenException();       // race-condition safe
            }

            event(new BookingCreated($booking));
            return $booking;
        });
    }
}
```
May: transactions, model writes, events, domain exceptions, call Services. May not: access request/session/auth globals (data arrives via DTO), render responses.

### Model
Relations, `casts()` (enums, dates, encrypted), query scopes (`scopePublished`), accessors. **No business methods** — `$order->markAsPaid()` belongs in an Action.

### API Resource
Every prop crossing to React. Slim `*CardResource` variants for lists (only card fields). Conditional fields via `when()` / `whenLoaded()` — `whenLoaded` also guards against N+1 leaks.

## 4. Eloquent relations — required patterns

- Define **both sides** of every relation, fully typed return: `public function items(): HasMany`.
- FK columns follow `{relation}_id`; custom keys documented in SCHEMA.md.
- Pivots: `belongsToMany(...)->withTimestamps()`; extra pivot columns → dedicated Pivot class with casts.
- Prefer explicit relation queries for aggregation: `withCount()`, `withSum()`, `withExists()` — never count in PHP loops.
- Ownership traversal for authorization: `$user->orders()->findOrFail($id)` (IDOR-safe by construction).
- Polymorphic relations require an enum morph map (`Relation::enforceMorphMap`) — no ::class strings in DB.
- Deletes: decide per model — cascade in DB for true children (order_items), `SoftDeletes` for user-facing entities (listings, bookings) with pruning policy.

## 5. Routing standard

```php
// routes/web.php — public
Route::middleware('throttle:public')->group(function () {
    Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/{listing:slug}', [ListingController::class, 'show'])->name('listings.show');
});

// authenticated
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('bookings', BookingController::class)->except('edit', 'update');
});

// routes/admin.php — loaded with prefix('admin')->name('admin.')->middleware(['auth','verified','role:admin'])
Route::resource('listings', Admin\ListingController::class);
```
Rules: every route named · slugs/ULIDs in public URLs (no enumerable integer IDs) · `scopeBindings()` on nested resources · route files split by concern (`web.php`, `admin.php`, `api.php`) · fallback route → branded 404.

## 6. Idempotent creation (reference implementation)

For payment/order/booking endpoints:
1. Client sends `Idempotency-Key` (UUID generated per form render; Inertia `useForm` includes it as hidden field; button disabled while `processing`).
2. `EnsureIdempotency` middleware: `Cache::lock("idem:{key}", 10)` → if a stored response exists for the key (24h TTL), replay it; otherwise continue and store the response.
3. DB unique constraint remains the final guard; Action converts violation → domain exception → friendly error.

## 7. Errors & observability

- Domain exceptions extend `DomainException`, carry a user-safe message, mapped in `bootstrap/app.php` `->withExceptions()` to flash/422 — never 500.
- Structured logging with context (`Log::info('order.placed', ['order_id' => …])`). Slow query log > 100ms in staging.
- Health endpoint `/up` (built-in). Pulse in production for p95 tracking.

## 8. Frontend architecture (Inertia + React)

- Pages are presentational. Data mutations only via `useForm`/`router` — no fetch/axios to internal endpoints (bypasses Inertia state).
- Shared global props (auth user, flash, locale) typed once in `types/index.d.ts` via `PageProps`.
- Filters/pagination: `router.get(url, params, { only: ['listings'], preserveState: true, preserveScroll: true, replace: true })`.
- Forms: server errors surface from `errors` prop; client-side validation is UX sugar, never the gate.
- i18n/RTL: locale from shared props; `dir` set on `<html>`; localized model fields resolved server-side in Resources (`title` returns correct language, `title_raw` object only when editing).

## 9. Scale triggers (when to deviate — document in PRD.md)

| Requirement | Deviation |
|---|---|
| Mobile app / 3rd-party API consumers | Add `routes/api.php` + Sanctum tokens; Resources already reusable |
| Multi-tenant SaaS | `stancl/tenancy` or scoped `team_id` + global scopes; decide day 0, not later |
| Real-time (chat, live boards) | Reverb + Echo, presence channels |
| Search across large text corpus | Scout + Meilisearch/Typesense; never `LIKE %…%` on big tables |
| >~50 req/s sustained or heavy CPU | Octane (FrankenPHP/Swoole), read replicas, horizontal queue workers |
| Complex reporting | Read models / summary tables via scheduled jobs; never live aggregation |
