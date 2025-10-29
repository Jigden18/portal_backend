<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ChatSearchController extends Controller
{
    protected int $cacheTtl;

    public function __construct()
    {
        // Middleware applied via route; no constructor middleware
        $this->cacheTtl = (int) env('CHAT_SEARCH_CACHE_TTL', 20);
    }

    /**
     * Minimal search for dropdown as user types.
     *
     * Query params:
     * - q: required, search string
     * - limit: optional, max results per type (default 5)
     *
     * Returns JSON:
     * {
     *   "results": [
     *       {id, type, name, avatar}
     *   ]
     * }
     */
    public function search(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:50',
            'limit' => 'nullable|integer|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $q = trim($request->input('q'));
        $limit = (int) $request->input('limit', 5);

        if (preg_match('/^[%_\s]+$/u', $q)) {
            return response()->json([
                'errors' => ['q' => ['Query must contain at least one alphanumeric character.']]
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $cacheKey = 'chat_dropdown_search:' . md5(auth()->id() . '|' . strtolower($q) . '|' . $limit);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($q, $limit) {
            // Split search into words for AND matching
            $words = array_values(array_filter(explode(' ', preg_replace('/\s+/u', ' ', $q))));

            $buildWhereWords = function ($query, $column) use ($words) {
                foreach ($words as $word) {
                    $query->whereRaw('LOWER(' . $column . ') LIKE ?', ['%' . mb_strtolower($word, 'UTF-8') . '%']);
                }
            };

            // Search Profiles
            $profiles = Profile::query()
                ->select(['id', DB::raw('full_name as name'), 'photo_url'])
                ->where(function ($q) use ($buildWhereWords) {
                    $buildWhereWords($q, 'full_name');
                })
                ->orderBy('full_name')
                ->limit($limit)
                ->get()
                ->map(function($p) {
                    return [
                        'id' => $p->id,
                        'type' => 'profile',
                        'name' => $p->name,
                        'avatar' => $p->photo_url,
                    ];
                });

            // Search Organizations
            $organizations = Organization::query()
                ->select(['id', DB::raw('name as name'), 'logo_url'])
                ->where(function ($q) use ($buildWhereWords) {
                    $buildWhereWords($q, 'name');
                })
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(function($o) {
                    return [
                        'id' => $o->id,
                        'type' => 'organization',
                        'name' => $o->name,
                        'avatar' => $o->logo_url,
                    ];
                });

            // Combine and sort by name
            $results = $profiles->merge($organizations)
                                ->sortBy('name')
                                ->values(); // reindex

            return [
                'results' => $results
            ];
        });
    }
}
