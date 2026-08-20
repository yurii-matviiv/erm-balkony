<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Vacancy;
use App\Models\VacancyApplication;
use App\Models\VacancyApplicationMarketingData;
use App\Models\VacancyFormView;
use App\Services\Marketing\FormAttribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public, unauthenticated landing form for job applications ("набір
 * персоналу") — the destination an Instagram/Facebook ad button points to.
 * One form per vacancy, addressed by `Vacancy.slug` (e.g. /vacancy/montazhnyk)
 * so each ad campaign can link straight to its own position, with no extra
 * selection step for the candidate. Kept intentionally separate from any
 * future "advert page with conditions + a button to the form" — see
 * CLAUDE.md, "Форма набору персоналу", for how that's expected to slot in
 * later without touching this controller.
 *
 * Every GET is logged as a "view" (VacancyFormView) BEFORE the candidate
 * decides whether to fill anything in — this is what lets "Воронка
 * вакансій" show views → unique visitors → submissions as a real funnel,
 * not just a count of completed applications. A long-lived cookie
 * (FormAttribution::visitorId()) ties repeat visits from the same browser
 * together as one "unique visitor".
 *
 * Duplicate-application protection is deliberately by PHONE NUMBER, not by
 * the visitor cookie: the cookie only catches the same browser resubmitting
 * (e.g. a double click), while phone also catches the same person opening
 * the ad again on a different phone/computer — see
 * DUPLICATE_APPLICATION_COOLDOWN_DAYS below.
 */
class VacancyFormController extends Controller
{
    /**
     * How long a candidate who already applied to THIS vacancy is treated
     * as "already in the system" and blocked from creating a second
     * VacancyApplication. Deliberately not forever — the Candidate model's
     * own docblock expects genuine re-applications months later (e.g. the
     * vacancy re-opens) to go through as a new application, not be silently
     * swallowed. Matched by phone number (not the visitor cookie), because
     * the whole point is to catch the same person applying from a
     * different browser/device, where no cookie is shared.
     */
    private const DUPLICATE_APPLICATION_COOLDOWN_DAYS = 30;

    public function show(Request $request, string $slug): View
    {
        $vacancy = Vacancy::where('slug', $slug)->firstOrFail();

        VacancyFormView::create(array_merge(
            ['vacancy_id' => $vacancy->id],
            FormAttribution::captureFromRequest($request),
        ));

        return view('public.vacancy-form', [
            'vacancy' => $vacancy,
            'skillOptions' => VacancyApplication::skillOptions(),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $vacancy = Vacancy::where('slug', $slug)->firstOrFail();

        // Honeypot: a hidden field real visitors never see or fill in (see
        // resources/views/public/vacancy-form.blade.php). Bots that blindly
        // fill every input trip it. We silently pretend success — no
        // Candidate/VacancyApplication/marketing row gets created — so the
        // bot has no signal telling it to change behaviour.
        if ($request->filled('website') || ! $vacancy->is_active) {
            return redirect()->route('vacancy-form.show', $slug);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'skills' => ['array'],
            'skills.*' => ['string', Rule::in(array_keys(VacancyApplication::skillOptions()))],
            'skills_other' => ['nullable', 'string', 'max:1000'],
        ]);

        $phone = trim($data['phone']);

        // Same "search existing contact by phone, otherwise create" rule as
        // the admin panel's Candidate select — see VacancyApplicationResource.
        // Phone is intentionally not unique/normalized beyond trimming (see
        // create_candidates_table migration), so this matches on exact
        // string only, same as staff do when searching manually.
        $candidate = Candidate::firstOrCreate(
            ['phone' => $phone],
            ['first_name' => $data['first_name']],
        );

        if (! $candidate->wasRecentlyCreated && blank($candidate->first_name)) {
            $candidate->update(['first_name' => $data['first_name']]);
        }

        // Same phone already applied to this exact vacancy recently — most
        // likely the same person opening the ad again (possibly on another
        // device, which is why this is checked by phone and not by the
        // visitor cookie). Don't create a second application; just tell
        // them we already have it.
        $alreadyApplied = VacancyApplication::query()
            ->where('candidate_id', $candidate->id)
            ->where('vacancy_id', $vacancy->id)
            ->where('created_at', '>=', now()->subDays(self::DUPLICATE_APPLICATION_COOLDOWN_DAYS))
            ->exists();

        if ($alreadyApplied) {
            return redirect()
                ->route('vacancy-form.thanks', $slug)
                ->with('already_applied', true);
        }

        $application = VacancyApplication::create([
            'candidate_id' => $candidate->id,
            'vacancy_id' => $vacancy->id,
            'advertising_channel' => FormAttribution::guessChannel($request),
            'is_targeted' => true,
            'skills' => $data['skills'] ?? [],
            'skills_other' => $data['skills_other'] ?? null,
        ]);

        VacancyApplicationMarketingData::create(array_merge(
            ['vacancy_application_id' => $application->id],
            FormAttribution::captureFromRequest($request),
        ));

        return redirect()->route('vacancy-form.thanks', $slug);
    }

    public function thanks(string $slug): View
    {
        $vacancy = Vacancy::where('slug', $slug)->firstOrFail();

        return view('public.vacancy-form-thanks', [
            'vacancy' => $vacancy,
            // Flashed by store() when this exact candidate already has a
            // recent application for this vacancy — same URL, different
            // message, so a repeat submitter (e.g. from another device)
            // gets an honest "we already have you" instead of a silent
            // duplicate entry in the system.
            'alreadyApplied' => (bool) session('already_applied'),
        ]);
    }
}
