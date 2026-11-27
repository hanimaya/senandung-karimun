<?php

namespace App\Http\Controllers;

use App\Exports\ServiceFeedbackReportExport;
use App\Models\Guest;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ServiceFeedbackReportController extends Controller
{
    private const RATING_LABELS = [
        1 => 'Sangat Buruk',
        2 => 'Buruk',
        3 => 'Cukup',
        4 => 'Baik',
        5 => 'Sangat Baik',
    ];

    public function index(Request $request)
    {
        $this->ensureInstitutionScope();
        $filters = $this->validatedFilters($request);

        $query = $this->filteredFeedbackQuery($filters);
        $summary = (clone $query)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(service_feedback.rating), 0) as average_rating')
            ->first();

        $criteria = (clone $query)
            ->select('service_feedback.rating')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('service_feedback.rating')
            ->pluck('total', 'rating');

        $admins = $this->adminAggregates(clone $query)
            ->paginate(5, ['*'], 'admin_page')
            ->withQueryString();

        $feedbacks = (clone $query)
            ->leftJoin('users', 'users.id', '=', 'service_feedback.admin_user_id')
            ->select('service_feedback.*', 'users.name as admin_name')
            ->latest('service_feedback.created_at')
            ->paginate(5)
            ->withQueryString();

        $guestFilters = $this->validatedGuestFilters($request);
        $guestRatingsQuery = $this->filteredGuestRatingsQuery($guestFilters);
        $guestSummary = (clone $guestRatingsQuery)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(feedback_rating), 0) as average_rating')
            ->first();
        $guestCriteria = (clone $guestRatingsQuery)
            ->select('feedback_rating')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('feedback_rating')
            ->pluck('total', 'feedback_rating');
        $guestRatings = (clone $guestRatingsQuery)
            ->latest('feedback_submitted_at')
            ->paginate(5, ['*'], 'guest_page')
            ->withQueryString();

        return view('reports.service-feedback', [
            'filters' => $filters,
            'totalFeedback' => (int) ($summary->total ?? 0),
            'averageRating' => (float) ($summary->average_rating ?? 0),
            'criteria' => $criteria,
            'admins' => $admins,
            'feedbacks' => $feedbacks,
            'guestFilters' => $guestFilters,
            'totalGuestRatings' => (int) ($guestSummary->total ?? 0),
            'averageGuestRating' => (float) ($guestSummary->average_rating ?? 0),
            'guestCriteria' => $guestCriteria,
            'guestRatings' => $guestRatings,
            'ratingLabels' => self::RATING_LABELS,
        ]);
    }

    public function export(Request $request)
    {
        $this->ensureInstitutionScope();
        $filters = $this->validatedFilters($request);
        $guestFilters = $this->validatedGuestFilters($request);
        $admins = $this->adminAggregates(clone $this->filteredFeedbackQuery($filters))->get();
        $guestRatings = $this->filteredGuestRatingsQuery($guestFilters)
            ->latest('feedback_submitted_at')
            ->get();

        return Excel::download(
            new ServiceFeedbackReportExport($admins, $guestRatings),
            'rekap-penilaian-layanan.xlsx'
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }

    private function validatedGuestFilters(Request $request): array
    {
        return $request->validate([
            'guest_search' => ['nullable', 'string', 'max:150'],
            'guest_rating' => ['nullable', 'integer', 'between:1,5'],
            'guest_from' => ['nullable', 'date'],
            'guest_to' => ['nullable', 'date', 'after_or_equal:guest_from'],
        ]);
    }

    private function filteredGuestRatingsQuery(array $filters)
    {
        $query = Guest::forUser(auth()->user())
            ->whereNotNull('feedback_rating');

        if (!empty($filters['guest_search'])) {
            $search = $filters['guest_search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('agency', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['guest_rating'])) {
            $query->where('feedback_rating', $filters['guest_rating']);
        }

        if (!empty($filters['guest_from'])) {
            $query->whereDate('feedback_submitted_at', '>=', $filters['guest_from']);
        }

        if (!empty($filters['guest_to'])) {
            $query->whereDate('feedback_submitted_at', '<=', $filters['guest_to']);
        }

        return $query;
    }

    private function adminAggregates(Builder $query): Builder
    {
        return $query
            ->leftJoin('users', 'users.id', '=', 'service_feedback.admin_user_id')
            ->select('service_feedback.admin_nip')
            ->selectRaw("COALESCE(MAX(users.name), 'Akun tidak tersedia') as admin_name")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('ROUND(AVG(service_feedback.rating), 2) as average_rating')
            ->selectRaw('SUM(CASE WHEN service_feedback.rating = 1 THEN 1 ELSE 0 END) as rating_1')
            ->selectRaw('SUM(CASE WHEN service_feedback.rating = 2 THEN 1 ELSE 0 END) as rating_2')
            ->selectRaw('SUM(CASE WHEN service_feedback.rating = 3 THEN 1 ELSE 0 END) as rating_3')
            ->selectRaw('SUM(CASE WHEN service_feedback.rating = 4 THEN 1 ELSE 0 END) as rating_4')
            ->selectRaw('SUM(CASE WHEN service_feedback.rating = 5 THEN 1 ELSE 0 END) as rating_5')
            ->groupBy('service_feedback.admin_nip')
            ->orderByDesc('total');
    }

    private function filteredFeedbackQuery(array $filters): Builder
    {
        $query = DB::table('service_feedback');

        $user = auth()->user();
        if ($user->isInstitutionAdmin()) {
            $query->whereExists(function (Builder $subquery) use ($user): void {
                $subquery->selectRaw('1')
                    ->from('users')
                    ->whereColumn('users.id', 'service_feedback.admin_user_id')
                    ->where('users.institution_id', $user->institution_id);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('service_feedback.admin_nip', 'like', "%{$search}%")
                    ->orWhereExists(function (Builder $subquery) use ($search): void {
                        $subquery->selectRaw('1')
                            ->from('users')
                            ->whereColumn('users.id', 'service_feedback.admin_user_id')
                            ->where('users.name', 'like', "%{$search}%");
                    });
            });
        }

        if (!empty($filters['rating'])) {
            $query->where('service_feedback.rating', $filters['rating']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('service_feedback.created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('service_feedback.created_at', '<=', $filters['to']);
        }

        return $query;
    }

    private function ensureInstitutionScope(): void
    {
        $user = auth()->user();
        abort_unless($user->isSuperAdmin() || ($user->isInstitutionAdmin() && $user->institution_id), 403);
    }
}
