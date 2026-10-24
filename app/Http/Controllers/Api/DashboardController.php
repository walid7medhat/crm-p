<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Area;
use App\Models\PropertyType;
use App\Models\UnitView;
use App\Models\Owner;
use App\Models\Developer;
use App\Models\LayoutType;
use App\Models\ListingAccessRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;
use App\Models\HotDealRequest;
use App\Models\Stage;
use App\Models\Lead;
use App\Models\Deal;
use App\Models\DealProperty;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * @return array{0: ?\Carbon\Carbon, 1: ?\Carbon\Carbon}
     */
    private function resolveDashboardDateRange(Request $request): array
    {
        $from = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : null;
        $to = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : null;

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function applyCreatedBetween($query, ?Carbon $from, ?Carbon $to, string $column = 'created_at')
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }
        if ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }

public function index(Request $request)
{
    $query = Activity::query()->with(['causer','subject' => function ($morphTo) {
            $morphTo->morphWith([
                Listing::class => ['area'], 
            ]);
        }]);
    // Filter by model (log_name)
    if ($request->model) {
        $query->where('log_name', $request->model);
    }
    
    // Filter by date range
    if ($request->date_from) {
        $query->whereDate('created_at', '>=', $request->date_from);
    }
    
    if ($request->date_to) {
        $query->whereDate('created_at', '<=', $request->date_to);
    }
    
    // Filter by user
    if ($request->user_id) {
        $query->where('causer_id', $request->user_id);
    }
    
    $logs = $query->latest()->get();
    
    return response()->json($logs);
}public function getStats(Request $request)
{
    [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);

    $hasDateFilter = $rangeFrom || $rangeTo;

    $changeFrom = $hasDateFilter ? $rangeFrom : null;
    $changeTo   = $hasDateFilter ? $rangeTo   : null;

    $currentUser = auth()->user();

    $user_herarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
          ->orWhere('parent_id', $currentUser->id)
          ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
              $parentQuery->where('parent_id', $currentUser->id);
          });
    })->pluck('id')->toArray();

    // ================= Agents =================
    $agentsBase = User::when(
        !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
        fn($q) => $q->whereIn('parent_id', $user_herarchy)
    );

    $totalAgents = (clone $agentsBase)
        ->when($hasDateFilter, fn ($q) => $this->applyCreatedBetween($q, $rangeFrom, $rangeTo))
        ->count();

    $agentsChange = (clone $agentsBase)
        ->when($changeFrom, fn ($q) => $q->where('created_at', '>=', $changeFrom))
        ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
        ->count();

    // ================= Listings =================
    $listingsBase = Listing::where('is_active', true)
        ->where('is_archived', false)
        ->whereNotIn('status', ['converted','draft','rented'])->where('approved', true);

    $totalListings = (clone $listingsBase)
        ->when($hasDateFilter, fn ($q) => $this->applyCreatedBetween($q, $rangeFrom, $rangeTo))
        ->count();

    $listingsChange = (clone $listingsBase)
        ->when($changeFrom, fn ($q) => $q->where('created_at', '>=', $changeFrom))
        ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
        ->count();

    // ================= Orders =================
    $ordersBase = ListingAccessRequest::when(
        !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
        fn($q) => $q->where('requested_by', auth()->id())
    );

    $myOrders = (clone $ordersBase)
        ->when($hasDateFilter, fn ($q) => $this->applyCreatedBetween($q, $rangeFrom, $rangeTo))
        ->count();

    $ordersChange = (clone $ordersBase)
        ->when($changeFrom, fn ($q) => $q->where('created_at', '>=', $changeFrom))
        ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
        ->count();

    // ================= Requests =================
    $requestsBase = ListingAccessRequest::when(
        !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
        function($q) use ($user_herarchy) {
            $q->whereHas('listing', function ($query) use ($user_herarchy) {
                $query->whereIn('agent_id', $user_herarchy);
            });
        }
    );

    $myRequests = (clone $requestsBase)
        ->when($hasDateFilter, fn ($q) => $this->applyCreatedBetween($q, $rangeFrom, $rangeTo))
        ->count();

    $requestsChange = (clone $requestsBase)
        ->when($changeFrom, fn ($q) => $q->where('created_at', '>=', $changeFrom))
        ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
        ->count();

    // ================= Owners =================
    $ownersBase = Owner::when(
        !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
        fn($q) => $q->where('added_by', auth()->id())
    );

    $owners = (clone $ownersBase)
        ->when($hasDateFilter, fn ($q) => $this->applyCreatedBetween($q, $rangeFrom, $rangeTo))
        ->count();

    $ownersChange = (clone $ownersBase)
        ->when($changeFrom, fn ($q) => $q->where('created_at', '>=', $changeFrom))
        ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
        ->count();

    // ================= Static Counts =================
    $developers      = Developer::count();
    $property_types  = PropertyType::count();
    $unit_views      = UnitView::count();
    $areas           = Area::count();
    $layout_types    = LayoutType::count();

    return response()->json([
        'success' => true,
        'data' => [
            'total_agents'     => $totalAgents,
            'agents_change'    => $agentsChange,
            'total_listings'   => $totalListings,
            'listings_change'  => $listingsChange,
            'my_orders'        => $myOrders,
            'orders_change'    => $ordersChange,
            'my_requests'      => $myRequests,
            'requests_change'  => $requestsChange,
            'owners'           => $owners,
            'owners_change'    => $ownersChange,
            'developers'       => $developers,
            'property_types'   => $property_types,
            'unit_views'       => $unit_views,
            'areas'            => $areas,
            'layout_types'     => $layout_types,
            'is_filtered'      => $hasDateFilter,
        ]
    ]);
}
  public function getListingsStatistics(Request $request)
{
    $period = $request->get('period', 'yearly');
    $currentUser = auth()->user();
    $user_hierarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
        ->orWhere('parent_id', $currentUser->id)
        ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
            $parentQuery->where('parent_id', $currentUser->id);
        });
    })->pluck('id')->toArray();

    // متوسط سعر العقارات
    $averagePrice = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('agent_id', $user_hierarchy);
    })->avg('price') ?? 0;

    // النسبة المئوية للنمو
    $currentMonthListings = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('agent_id', $user_hierarchy);
    })->whereYear('created_at', now()->year)
      ->whereMonth('created_at', now()->month)
      ->count();

    $lastMonthListings = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('agent_id', $user_hierarchy);
    })->whereYear('created_at', now()->subMonth()->year)
      ->whereMonth('created_at', now()->subMonth()->month)
      ->count();

    $growthPercentage = $lastMonthListings > 0 ? 
        (($currentMonthListings - $lastMonthListings) / $lastMonthListings) * 100 : 0;

    // التغير اليومي (متوسط العقارات المضافة يومياً)
    $dailyChange = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('agent_id', $user_hierarchy);
    })->whereDate('created_at', today())->count();

    $stats = [
        'average_price' => round($averagePrice),
        'growth_percentage' => round($growthPercentage, 1),
        'daily_change' => $dailyChange,
    ];

    [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);
    $chartData = ($rangeFrom && $rangeTo)
        ? $this->getChartDataByDateRange($rangeFrom, $rangeTo, $currentUser, $user_hierarchy)
        : $this->getChartDataByPeriod($period, $currentUser, $user_hierarchy);

    $performanceChart = $this->getTopListingPerformanceChart($currentUser, $user_hierarchy, $rangeFrom, $rangeTo);

    return response()->json([
        'success' => true,
        'data' => $stats,
        'chart_data' => $chartData,
        'performance_chart' => $performanceChart,
    ]);
}

private function getTopListingPerformanceChart($currentUser, $user_hierarchy, ?Carbon $from, ?Carbon $to): array
{
    $listingCount = function ($q) use ($from, $to) {
        $q->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotIn('status', ['converted', 'draft']);
        if ($from || $to) {
            $this->applyCreatedBetween($q, $from, $to);
        }
    };

    $agents = User::query()
        ->when(! ($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function ($q) use ($user_hierarchy) {
            $q->whereIn('id', $user_hierarchy);
        })
        ->withCount(['listings as listings_count' => $listingCount])
        ->having('listings_count', '>', 0)
        ->orderByDesc('listings_count')
        ->limit(7)
        ->get(['id', 'name', 'display_name']);

    $points = [];
    $categories = [];
    $values = [];
    $idx = 1;

    foreach ($agents as $agent) {
        $x = $idx * 10;
        $categories[] = (string) $x;
        $values[] = (int) $agent->listings_count;
        $points[] = [
            'x' => $x,
            'agents_label' => $x,
            'listings' => (int) $agent->listings_count,
            'agent_name' => User::resolveDisplayName($agent),
        ];
        $idx++;
    }

    while ($idx <= 7) {
        $x = $idx * 10;
        $categories[] = (string) $x;
        $values[] = 0;
        $points[] = [
            'x' => $x,
            'agents_label' => $x,
            'listings' => 0,
            'agent_name' => '',
        ];
        $idx++;
    }

    $maxVal = max($values) ?: 0;
    $yMax = $maxVal > 0 ? (int) (ceil($maxVal / 50) * 50) : 250;
    if ($yMax < 50) {
        $yMax = 50;
    }

    return [
        'categories' => $categories,
        'values' => $values,
        'points' => $points,
        'y_max' => $yMax,
        'x_title' => '(Agents)',
    ];
}

private function getChartDataByPeriod($period, $currentUser, $user_hierarchy)
{
    $labels = [];
    $values = [];

    switch ($period) {
        case 'yearly':
            // بيانات السنة الحالية
            for ($i = 1; $i <= 12; $i++) {
                $count = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('agent_id', $user_hierarchy);
                })->whereYear('created_at', now()->year)
                  ->whereMonth('created_at', $i)
                  ->count();
                $labels[] = date('M', mktime(0, 0, 0, $i, 1));
                $values[] = $count;
            }
            break;

        case 'monthly':
            // بيانات آخر 30 يوم
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $count = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('agent_id', $user_hierarchy);
                })->whereDate('created_at', $date)
                  ->count();
                $labels[] = $date->format('d M');
                $values[] = $count;
            }
            break;

        case 'weekly':
            // بيانات آخر 7 أيام
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $count = Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('agent_id', $user_hierarchy);
                })->whereDate('created_at', $date)
                  ->count();
                $labels[] = $date->format('D');
                $values[] = $count;
            }
            break;
    }

    return [
        'labels' => $labels,
        'values' => $values
    ];
}

private function getChartDataByDateRange(Carbon $from, Carbon $to, $currentUser, $user_hierarchy): array
{
    $labels = [];
    $values = [];
    $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());

    $listingQuery = function () use ($currentUser, $user_hierarchy) {
        return Listing::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function ($q) use ($user_hierarchy) {
            $q->whereIn('agent_id', $user_hierarchy);
        });
    };

    if ($days <= 31) {
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $count = $listingQuery()->whereDate('created_at', $d)->count();
            $labels[] = $d->format('d M');
            $values[] = $count;
        }
    } else {
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $weekEnd = $cursor->copy()->addDays(6)->endOfDay();
            if ($weekEnd->gt($to)) {
                $weekEnd = $to->copy();
            }
            $count = $listingQuery()
                ->whereBetween('created_at', [$cursor->copy()->startOfDay(), $weekEnd])
                ->count();
            $labels[] = $cursor->format('d M');
            $values[] = $count;
            $cursor->addDays(7);
        }
    }

    return ['labels' => $labels, 'values' => $values];
}

public function getActiveAgents()
{
    $currentUser = auth()->user();
    $user_hierarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
        ->orWhere('parent_id', $currentUser->id)
        ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
            $parentQuery->where('parent_id', $currentUser->id);
        });
    })->pluck('id')->toArray();

    // إجمالي الوكلاء
    $totalAgents = User::
    // whereHas('roles', function($query) {
    //     $query->whereIn('name', ['sales','team_lead']);
    // })->
    when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('parent_id', $user_hierarchy);
    })->count();

    // النسبة المئوية للتغير
    $lastMonthAgents = User::
    // whereHas('roles', function($query) {
    //     $query->whereIn('name', ['sales','team_lead']);
    // })->
    when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('parent_id', $user_hierarchy);
    })->where('created_at', '<', now()->subMonth())
    ->count();

    $agentsChange = $lastMonthAgents > 0 ? 
        (($totalAgents - $lastMonthAgents) / $lastMonthAgents) * 100 : 0;

    // التغير اليومي (الوكلاء المضافين اليوم)
    $dailyChange = User::
    // whereHas('roles', function($query) {
    //     $query->whereIn('name', ['sales','team_lead']);
    // })->
    when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
        $q->whereIn('parent_id', $user_hierarchy);
    })->whereDate('created_at', today())->count();

    // بيانات الأسبوع
    $weeklyData = [];
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i);
        $count = User::
        // whereHas('roles', function($query) {
        //     $query->whereIn('name', ['sales','team_lead']);
        // })->
        when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
            $q->whereIn('parent_id', $user_hierarchy);
        })->whereDate('created_at', $date)->count();
        $weeklyData[] = $count;
    }

    return response()->json([
        'success' => true,
        'data' => [
            'total_agents' => $totalAgents,
            'agents_change' => round($agentsChange, 1),
            'daily_change' => $dailyChange,
            'weekly_data' => $weeklyData
        ]
    ]);
}

public function getLeadsOverview(Request $request)
{
    $timeframe = $request->get('timeframe', 'today');
    $currentUser = auth()->user();
    $user_hierarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
        ->orWhere('parent_id', $currentUser->id)
        ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
            $parentQuery->where('parent_id', $currentUser->id);
        });
    })->pluck('id')->toArray();

    $newLeads = 0;
    $approvedLeads = 0;
    $rejectedLeads = 0;
    $totalLeads = 0;

    switch ($timeframe) {
        case 'today':
            // Leads الجديدة اليوم (طلبات جديدة)
            $newLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($currentUser) {
                $q->where('requested_by', $currentUser->id);
            })->whereDate('created_at', today())->count();

            // Leads المعتمدة (الطلبات المقبولة)
            $approvedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'approved')
            ->whereDate('created_at', today())->count();

            // Leads المرفوضة
            $rejectedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'rejected')
            ->whereDate('created_at', today())->count();

            break;

        case 'weekly':
            // أسبوعي
            $newLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($currentUser) {
                $q->where('requested_by', $currentUser->id);
            })->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

            $approvedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'approved')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

            $rejectedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'rejected')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

            break;

        case 'monthly':
            // شهري
            $newLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($currentUser) {
                $q->where('requested_by', $currentUser->id);
            })->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year)
              ->count();

            $approvedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'approved')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

            $rejectedLeads = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($user_hierarchy) {
                $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                    $query->whereIn('agent_id', $user_hierarchy);
                });
            })->where('status', 'rejected')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

            break;
        case 'yearly':
                $newLeads = ListingAccessRequest::when(
                    !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
                    fn($q) => $q->where('requested_by', $currentUser->id)
                )
                ->whereYear('created_at', now()->year)
                ->count();

                $approvedLeads = ListingAccessRequest::when(
                    !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
                    function($q) use ($user_hierarchy) {
                        $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                            $query->whereIn('agent_id', $user_hierarchy);
                        });
                    }
                )
                ->where('status', 'approved')
                ->whereYear('created_at', now()->year)
                ->count();

                $rejectedLeads = ListingAccessRequest::when(
                    !($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')),
                    function($q) use ($user_hierarchy) {
                        $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                            $query->whereIn('agent_id', $user_hierarchy);
                        });
                    }
                )
                ->where('status', 'rejected')
                ->whereYear('created_at', now()->year)
                ->count();

                break;
    }

    $totalLeads = $newLeads + $approvedLeads + $rejectedLeads;

    return response()->json([
        'success' => true,
        'data' => [
            'new_leads' => $newLeads,
            'approved_leads' => $approvedLeads,
            'rejected_leads' => $rejectedLeads,
            'total_leads' => $totalLeads
        ]
    ]);
}

// في DashboardController
public function getMyLatestOrders()
{
    $currentUser = auth()->user();
    
    // الطلبات اللي اليوزر عملها (My Orders)
    $orders = ListingAccessRequest::with(['listing.propertyType'])
        ->when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function($q) use ($currentUser) {
                $q->where('requested_by', $currentUser->id);
            })
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get()
        ->map(function ($request) {
            return [
                'id' => $request->id,
                'purpose' => $request->request_type ?? 'Property Access',
                'status' => $request->status,
                'created_at' => $request->created_at,
                  // Request Date
                    'requested_at' => $request->created_at,
            
                    // Response Date
                    'responded_at' => in_array($request->status, ['approved', 'rejected'])
                        ? $request->updated_at
                        : null,

                  'listing' => [
                        'title' => $request->listing->title ?? 'N/A',
                        'area' => [
                            'name' => $request->listing->area->name ?? 'N/A'
                        ],
                        'property_type' => [
                            'name' => $request->listing->propertyType->name ?? 'N/A'
                        ],
                        'agent' => [
                            'name' => User::resolveDisplayName($request->listing->agent) ?? 'N/A',
                            'avatar' => $request->listing->agent ?  asset('storage/'. $request->listing->agent->avatar) : null,
                        ]
                    ]
            ];
        });

    return response()->json([
        'success' => true,
        'data' => $orders
    ]);
}

public function getMyLatestRequests()
{
    $currentUser = auth()->user();
    $user_hierarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
        ->orWhere('parent_id', $currentUser->id)
        ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
            $parentQuery->where('parent_id', $currentUser->id);
        });
    })->pluck('id')->toArray();

    // الطلبات اللي طلبوها من اليوزر (My Requests)
   $requests = ListingAccessRequest::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_hierarchy){
             $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                $query->where(function($q) use ($user_hierarchy) {
                $q->orWhereIn('agent_id', $user_hierarchy);
            });
        });})
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get()
    ->map(function ($request) {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'request_from' => [
                'name' => User::resolveDisplayName($request->requestedBy) ?? 'N/A',
                'email' => $request->requestedBy->email ?? 'N/A',
                'avatar' => $request->requestedBy->avatar ? asset('storage/'. $request->requestedBy->avatar) : 'N/A',
          
            ],
            'request_to' => [
                'name' => User::resolveDisplayName($request->listing->agent) ?? 'N/A',
                'email' => $request->listing->agent->email ?? 'N/A',
                  'avatar' => $request->listing->agent ?  asset('storage/'. $request->listing->agent->avatar) : null,
                
            ],
            'listing' => [
                        'title' => $request->listing->title ?? 'N/A',
                        'area' => [
                            'name' => $request->listing->area->name ?? 'N/A'
                        ],
                        'property_type' => [
                            'name' => $request->listing->propertyType->name ?? 'N/A'
                        ],
                    ]
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $requests
    ]);
}


// في DashboardController
public function getTopAgentPerformance(Request $request)
{
    [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);
    $currentUser = auth()->user();
    
    $user_hierarchy = [];
    
    // if ($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')) {
        // Admin يشوف كل users
        $user_hierarchy = User::pluck('id')->toArray();
    // } else {
    //     // نجيب أعلى مدير في السلسلة
    //     $topManagerId = $this->getTopManagerId($currentUser);
        
    //     // نجيب كل الفريق تحت هذا المدير
    //     $user_hierarchy = User::where(function($q) use ($topManagerId) {
    //         $q->where('id', $topManagerId)
    //         ->orWhere('parent_id', $topManagerId)
    //         ->orWhereHas('parent', function($parentQuery) use ($topManagerId) {
    //             $parentQuery->where('parent_id', $topManagerId);
    //         })
    //         ->orWhereHas('parent.parent', function($parentQuery) use ($topManagerId) {
    //             $parentQuery->where('parent_id', $topManagerId);
    //         });
    //     })->pluck('id')->toArray();
    // }

    // جلب أفضل الوكلاء
    $topAgents = User::whereIn('id', $user_hierarchy)
    ->with(['roles', 'employeeProfile.companyBranch'])
    ->withCount([
        'listings' => function ($q) use ($rangeFrom, $rangeTo) {
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
            }
        },
        'approvedRequests' => function ($q) use ($rangeFrom, $rangeTo) {
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
            }
        },
    ])
    ->orderBy('listings_count', 'desc')
    ->orderBy('approved_requests_count', 'desc')
    ->limit(5)
    ->get()
    ->map(function ($user) use ($currentUser) {
        return [
            'id' => $user->id,
            'name' => User::resolveDisplayName($user),
            'email' => $user->email,
            'avatar' => $user->avatar ?  asset('storage/'. $user->avatar) : null,
            'role' => $user->roles->first()->name ?? 'Agent',
            'office' => $user->employeeProfile?->companyBranch?->name ?? 'Head Office',
            'department' => $user->employeeProfile?->companyBranch?->name ?? 'Head Office',
            'listings_count' => $user->listings_count,
            'approved_requests' => $user->approved_requests_count,
            'is_current_user' => $user->id === $currentUser->id
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $topAgents
    ]);
}

private function getTopManagerId($user)
{
    $currentId = $user->id;
    $parentId = $user->parent_id;
    
    // بنسير لأعلى لحد ما نوصل لأعلى مدير
    while ($parentId) {
        $parent = User::find($parentId);
        if ($parent && $parent->parent_id && !$parent->hasRole(['super_admin', 'admin'])) {
            $currentId = $parentId;
            $parentId = $parent->parent_id;
        } else {
            break;
        }
    }
    
    return $currentId;
}

public function getAdminLatestRequests()
{
    $currentUser = auth()->user();

    $user_hierarchy = User::where(function($q) use ($currentUser) {
        $q->where('id', $currentUser->id)
          ->orWhere('parent_id', $currentUser->id)
          ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
              $parentQuery->where('parent_id', $currentUser->id);
          });
    })->pluck('id')->toArray();

    $requests = ListingAccessRequest::with([
        'requestedBy:id,name,display_name,email,avatar',
        'listing:id,title,agent_id,property_type_id,area_id',
        'listing:id,title,agent_id,property_type_id',
        'listing.agent:id,name,display_name,email,avatar',
        'listing.propertyType:id,name', 
        'listing.area:id,name' 
    ])
    ->when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function ($q) use ($user_hierarchy) {
        $q->whereHas('listing', function ($query) use ($user_hierarchy) {
            $query->whereIn('agent_id', $user_hierarchy);
        });
    })
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get()
    ->map(function ($request) {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'request_from' => [
                'name' => User::resolveDisplayName($request->requestedBy) ?? 'N/A',
                'email' => $request->requestedBy->email ?? 'N/A',
                                'avatar' => $request->requestedBy->avatar ? asset('storage/'. $request->requestedBy->avatar) : 'N/A',

            ],
            'request_to' => [
                'name' => User::resolveDisplayName($request->listing->agent) ?? 'N/A',
                'email' => $request->listing->agent->email ?? 'N/A',
                
                                'avatar' => $request->listing->agent->avatar ? asset('storage/'. $request->listing->agent->avatar) : 'N/A',

                
            ],
            'listing' => [
                'id' => $request->listing->id ?? null,
                'title' => $request->listing->title ?? 'N/A',
                'area' => [
                'name' => $request->listing->area->name ?? 'N/A' // هنا تجيب الاسم
            ],
                'property_type' => [ 
                    'name' => $request->listing->propertyType->name ?? 'N/A'
                ]
            ]
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $requests
    ]);
}

public function getPropertyTypesWithListings(Request $request)
{
    try {
        $user = Auth::user();
        $currentUser = $user;
        
        // Get user hierarchy for filtering
        $user_hierarchy = User::where(function($q) use ($currentUser) {
            $q->where('id', $currentUser->id)
            ->orWhere('parent_id', $currentUser->id)
            ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                $parentQuery->where('parent_id', $currentUser->id);
            });
        })->pluck('id')->toArray();

        // Query property types that have listings for this user
        $query = PropertyType::select('property_types.id', 'property_types.name')
            ->withCount(['listings as active_listings_count' => function($q) use ($user, $user_hierarchy) {
                // Apply conditions based on user role
                $q->when(!($user->hasRole('super_admin') || $user->hasRole('admin')), 
                    function($query) use ($user_hierarchy) {
                        $query->whereIn('agent_id', $user_hierarchy);
                    })
                ->where('is_active', true) // Only active listings
                ->where('status', '!=', 'converted') // Exclude converted
                ->where('status', '!=', 'rented')
                ->where('approved', true)
                ->where('status', '!=', 'draft') // Exclude draft
                ->where('is_archived', false); // Exclude archived
            }])
            ->whereHas('listings', function($q) use ($user, $user_hierarchy) {
                // Apply the same conditions in whereHas
                $q->when(!($user->hasRole('super_admin') || $user->hasRole('admin')), 
                    function($query) use ($user_hierarchy) {
                        $query->whereIn('agent_id', $user_hierarchy);
                    })
                ->where('is_active', true)
                ->where('status', '!=', 'converted')
                 ->where('status', '!=', 'rented')
                ->where('approved', true)
                ->where('status', '!=', 'draft')
                ->where('is_archived', false);
            });

        // Filter out property types with zero listings
        $propertyTypes = $query->orderBy('property_types.name')
            ->get()
            ->filter(function($propertyType) {
                return $propertyType->active_listings_count > 0;
            })
            ->map(function($propertyType) {
                return [
                    'id' => $propertyType->id,
                    'name' => $propertyType->name,
                    'listings_count' => $propertyType->active_listings_count
                ];
            })
            ->values(); // Reset array keys

        return response()->json([
            'success' => true,
            'data' => $propertyTypes
        ]);

    } catch (\Exception $e) {
        \Log::error('Error in getPropertyTypesWithListings: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch property types with listings',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function getListingsStatusSummary(Request $request)
    {
        [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);
        $currentUser = auth()->user();
        $userHierarchy = User::where(function ($q) use ($currentUser) {
            $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
        })->pluck('id')->toArray();

        $scoped = function () use ($currentUser, $userHierarchy, $rangeFrom, $rangeTo) {
            $query = Listing::query();
            if (! ($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin'))) {
                $query->whereIn('agent_id', $userHierarchy);
            }
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($query, $rangeFrom, $rangeTo);
            }

            return $query;
        };

        $soldOut = $scoped()
            ->whereIn('status', ['converted', 'rented'])
            ->count();

        $active = $scoped()
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotIn('status', ['converted', 'draft', 'rented'])
            ->where('approved', true)
            ->count();

        $inactive = $scoped()
            ->where(function ($q) {
                $q->where('is_archived', true)
                    ->orWhere('is_active', false)
                    ->orWhereIn('status', ['draft']);
            })
            ->count();

        $total = $soldOut + $active + $inactive;

        return response()->json([
            'success' => true,
            'data' => [
                'sold_out' => $soldOut,
                'active' => $active,
                'inactive' => $inactive,
                'total' => $total,
            ],
        ]);
    }

    public function getKanbanTaskSummary(Request $request)
    {
        [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);

        $stages = Stage::query()
            ->where('stage_type', 'lead')
            ->withCount(['leads' => function ($q) use ($rangeFrom, $rangeTo) {
                if ($rangeFrom || $rangeTo) {
                    $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
                }
            }])
            ->orderBy('order')
            ->get(['id', 'name', 'order']);

        $pick = function (array $needles) use ($stages) {
            $total = 0;

            foreach ($stages as $stage) {
                $name = strtolower((string) $stage->name);

                foreach ($needles as $needle) {
                    if (str_contains($name, strtolower($needle))) {
                        $total += (int) $stage->leads_count;
                        break; // عشان مايتحسبش مرتين
                    }
                }
            }

            return $total;
        };
        return response()->json([
            'success' => true,
            'data' => [
                'new' => $pick(['New Lead', 'fresh', 'incoming']),
                'assigned' => $pick(['Assigned', 'Contacted', 'Qualified']),
                'deal_won' => $pick(['won', 'closed', 'Converted', 'deal']),
                'stages' => $stages->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'count' => (int) $s->leads_count,
                ])->values(),
            ],
        ]);
    }

    public function getDashboardSchedule(Request $request)
    {
        [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);
        $date = $request->get('date', $rangeTo?->toDateString() ?? now()->toDateString());
        $day = Carbon::parse($date);

        $orders = ListingAccessRequest::with(['requestedBy:id,name,display_name,avatar', 'listing:id,title'])
            ->when($rangeFrom && $rangeTo, function ($q) use ($rangeFrom, $rangeTo) {
                $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
            }, function ($q) use ($day) {
                $q->whereDate('created_at', $day);
            })
            ->orderBy('created_at')
            ->limit(6)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'time' => $item->created_at?->format('g:i A'),
                    'title' => $item->listing?->title ?? 'Listing request',
                    'user' => [
                        'name' => User::resolveDisplayName($item->requestedBy),
                        'avatar' => $item->requestedBy?->avatar
                            ? asset('storage/'.$item->requestedBy->getRawOriginal('avatar'))
                            : null,
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('l, j F'),
                'items' => $orders,
            ],
        ]);
    }

 public function getSidebarCounts(Request $request)
    {
        try {
            $user = Auth::user();

            // Per-user short TTL: badges are user/role/hierarchy scoped. 45s is safe for nav counts.
            $cacheKey = 'sidebar_counts:u'.$user->id;
            $counts = Cache::remember($cacheKey, 45, function () use ($user) {
                $currentUser = $user;
                // Self, direct reports, and their reports. Two parent_id lookups
                // match the old whereHas('parent') without a correlated subquery.
                $childIds = User::query()->where('parent_id', $currentUser->id)->pluck('id');
                $grandchildIds = $childIds->isEmpty()
                    ? collect()
                    : User::query()->whereIn('parent_id', $childIds)->pluck('id');
                $user_hierarchy = $childIds
                    ->merge($grandchildIds)
                    ->push($currentUser->id)
                    ->unique()
                    ->values()
                    ->all();

                $requests = ListingAccessRequest::when(!($currentUser->hasRole('super_admin') || $currentUser->hasRole('admin')), function ($q) use ($user_hierarchy, $user) {
                    $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                        $query->where(function ($q) use ($user_hierarchy) {
                            $q->orWhereIn('agent_id', $user_hierarchy);
                        });
                    })->orWhere('handled_by', $user->id);
                })
                    ->count();

                // count() does not hydrate relations — drop unused with() that only wasted intent/clarity.
                $orders = ListingAccessRequest::when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function ($q) use ($user_hierarchy) {
                    $q->whereIn('requested_by', $user_hierarchy);
                })
                    ->orderBy('created_at', 'desc')
                    ->count();

                $hot_deals = HotDealRequest::when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function ($q) use ($user_hierarchy) {
                    $q->whereIn('requested_by', $user_hierarchy);
                })->where('status', 'pending')->orderBy('created_at', 'desc')
                    ->count();

                $query = Listing::where('approved', false)
                    ->where('status', 'published')
                    ->where('is_archived', false);

                if ($user->hasRole('team_lead')) {
                    $query->whereIn('agent_id', $user_hierarchy);
                }

                $needApprove = $query->count();

                return [
                    'listings' => [
                        'all' => Listing::where('is_active', true)->where('is_archived', false)->whereNotIn('status', ['converted', 'draft', 'rented'])
                            ->where('approved', true)->count(),
                        'my' => Listing::whereIn('agent_id', $user_hierarchy)->count(),
                        'archive' => Listing::where('status', 'archived')->count(),
                    ],
                    'requests' => [
                        'all' => $requests,
                    ],
                    'orders' => [
                        'all' => $orders,
                    ],
                    'hot_deals' => [
                        'all' => $hot_deals,
                    ],
                    'needapprove' => [
                        'all' => $needApprove,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $counts,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch sidebar counts', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sidebar counts'
            ], 500);
        }
    }

    /**
     * Unified analytics payload for /home executive dashboard.
     */
    public function getAnalyticsOverview(Request $request)
    {
        try {
            return $this->buildAnalyticsOverviewResponse($request);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics',
            ], 500);
        }
    }

    /**
     * Split-out endpoints so each dashboard band can load (and render) independently
     * instead of all four waiting on one combined response.
     */
    public function getAnalyticsCrm(Request $request)
    {
        try {
            [$rangeFrom, $rangeTo, $currentUser, $userHierarchy, $isAdmin] = $this->analyticsContext($request);

            // Short-lived cache per date range: super_admin has its own entry, admin its
            // own, and everyone else caches per user (their own hierarchy).
            $cacheScope = match (true) {
                $currentUser->hasRole('super_admin') => 'super_admin',
                $currentUser->hasRole('admin') => 'admin',
                default => 'user' . $currentUser->id,
            };
            $cacheKey = sprintf(
                'analytics:crm:%s:%s:%s',
                $cacheScope,
                $rangeFrom?->toDateTimeString() ?? '-',
                $rangeTo?->toDateTimeString() ?? '-'
            );

            $crm = \Illuminate\Support\Facades\Cache::remember(
                $cacheKey,
                now()->addMinutes(2),
                fn () => $this->buildCrmAnalytics($currentUser, $userHierarchy, $isAdmin, $rangeFrom, $rangeTo)
            );

            return response()->json([
                'success' => true,
                'crm' => $crm,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to load CRM analytics'], 500);
        }
    }

    public function getAnalyticsDeals(Request $request)
    {
        try {
            [$rangeFrom, $rangeTo, $currentUser, $userHierarchy, $isAdmin] = $this->analyticsContext($request);

            return response()->json([
                'success' => true,
                'deals' => $this->buildDealsAnalytics($currentUser, $userHierarchy, $isAdmin, $rangeFrom, $rangeTo),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to load deals analytics'], 500);
        }
    }

    public function getAnalyticsListing(Request $request)
    {
        try {
            [$rangeFrom, $rangeTo, , $userHierarchy, $isAdmin] = $this->analyticsContext($request);

            return response()->json([
                'success' => true,
                'listing' => $this->buildListingAnalytics($userHierarchy, $isAdmin, $rangeFrom, $rangeTo),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to load listing analytics'], 500);
        }
    }

    public function getAnalyticsHr(Request $request)
    {
        try {
            [, , , $userHierarchy, $isAdmin] = $this->analyticsContext($request);

            return response()->json([
                'success' => true,
                'hr' => $this->buildHrAnalytics($userHierarchy, $isAdmin),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'Failed to load HR analytics'], 500);
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon, 2: User, 3: array<int>, 4: bool}
     */
    private function analyticsContext(Request $request): array
    {
        [$rangeFrom, $rangeTo] = $this->resolveAnalyticsPeriod($request);
        $currentUser = auth()->user();

        $userHierarchy = User::where(function ($q) use ($currentUser) {
            $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function ($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
        })->pluck('id')->toArray();

        $isAdmin = $currentUser->hasRole('super_admin') || $currentUser->hasRole('admin');

        return [$rangeFrom, $rangeTo, $currentUser, $userHierarchy, $isAdmin];
    }

    private function buildCrmAnalytics(User $currentUser, array $userHierarchy, bool $isAdmin, ?Carbon $rangeFrom, ?Carbon $rangeTo, ?array $salesMetrics = null): array
    {
        $scopeLeads = function ($query) use ($userHierarchy, $isAdmin, $rangeFrom, $rangeTo) {
            if (! $isAdmin) {
                $query->whereIn('responsible_person_id', $userHierarchy);
            }
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($query, $rangeFrom, $rangeTo);
            }

            return $query;
        };

        $leadBase = $scopeLeads(Lead::query());

        // Call answered / No answer only count leads in the Contacted stage (0 = none).
        $contactedStageId = (int) (Stage::query()
            ->where('stage_type', 'lead')
            ->whereRaw('LOWER(name) = ?', ['contacted'])
            ->value('id') ?? 0);

        // Converted = leads in the Converted stage (same as the board's column). It used
        // converted_at, which isn't set on every lead moved there — showed 0 vs 6 on the board.
        $convertedStageId = (int) (Stage::query()
            ->where('stage_type', 'lead')
            ->whereRaw('LOWER(name) = ?', ['converted'])
            ->value('id') ?? 0);

        // ONE pass for every plain counter (was ~10 separate COUNT/SUM queries — each a
        // full scan of the leads table for super admin / admin, whose scope is everyone).
        $agg = (clone $leadBase)
            ->toBase()
            ->selectRaw(
                'COUNT(*) AS total_leads,
                 SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS new_leads,
                 SUM(CASE WHEN stage_id = ? THEN 1 ELSE 0 END) AS converted,
                 SUM(CASE WHEN interaction_result = \'no_answer\' AND stage_id = ? THEN 1 ELSE 0 END) AS no_answer,
                 SUM(CASE WHEN interaction_result = \'answered\' AND stage_id = ? THEN 1 ELSE 0 END) AS answered,
                 SUM(CASE WHEN LOWER(priority) = \'cold\' OR LOWER(status_lead) = \'cold\' THEN 1 ELSE 0 END) AS cold,
                 SUM(CASE WHEN LOWER(priority) = \'warm\' OR LOWER(status_lead) = \'warm\' THEN 1 ELSE 0 END) AS warm,
                 SUM(CASE WHEN LOWER(priority) = \'hot\' OR LOWER(status_lead) = \'hot\' THEN 1 ELSE 0 END) AS hot,
                 SUM(CASE WHEN converted_at IS NOT NULL THEN COALESCE(budget_to, 0) ELSE 0 END) AS revenue_from_leads',
                [now()->subDays(7), $convertedStageId, $contactedStageId, $contactedStageId]
            )
            ->first();

        $totalLeads = (int) ($agg->total_leads ?? 0);
        $newLeads = (int) ($agg->new_leads ?? 0);

        $stageCountsById = (clone $leadBase)
            ->select('stage_id', DB::raw('count(*) as total'))
            ->whereNotNull('stage_id')
            ->groupBy('stage_id')
            ->pluck('total', 'stage_id');

        $countByStage = Stage::query()
            ->where('stage_type', 'lead')
            ->orderBy('order')
            ->get(['id', 'name'])
            ->map(fn ($s) => [
                'name' => $s->name,
                'count' => (int) ($stageCountsById[$s->id] ?? 0),
            ])
            ->values();

        $pickStage = function (array $needles) use ($countByStage) {
            $total = 0;
            foreach ($countByStage as $row) {
                $name = strtolower((string) $row['name']);
                foreach ($needles as $needle) {
                    // Match at the start of a word, not anywhere: "qualified" must not
                    // match "Unqualified" (Qualified showed Qualified + Unqualified).
                    if (preg_match('/\b' . preg_quote(strtolower($needle), '/') . '/', $name)) {
                        $total += (int) $row['count'];
                        break;
                    }
                }
            }

            return $total;
        };

        $converted = (int) ($agg->converted ?? 0);
        $lost = $pickStage(['lost', 'unqualified', 'junk', 'closed lost']);
        $negotiation = $pickStage(['negotiat', 'proposal', 'offer']);
        $qualified = $pickStage(['qualified', 'hot', 'warm']);
        $followUp = $pickStage(['follow', 'callback', 'scheduled']);
        $contacted = $pickStage(['contacted', 'assigned', 'in progress']);
        $noAnswer = (int) ($agg->no_answer ?? 0);
        $answered = (int) ($agg->answered ?? 0);

        $conversionRate = $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0;

        $leadSources = (clone $leadBase)
            ->select('lead_source', DB::raw('count(*) as total'))
            ->whereNotNull('lead_source')
            ->where('lead_source', '!=', '')
            ->groupBy('lead_source')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['source' => $r->lead_source ?: 'Unknown', 'count' => (int) $r->total])
            ->values();

        $agentCounts = (clone $leadBase)
            ->select(
                'responsible_person_id',
                DB::raw('count(*) as leads_count'),
                // "Won" = in the Converted stage, same as the Converted number above.
                DB::raw('sum(case when stage_id = ' . (int) $convertedStageId . ' then 1 else 0 end) as converted_count')
            )
            ->whereNotNull('responsible_person_id')
            ->groupBy('responsible_person_id')
            ->get();

        $agentUsers = User::whereIn('id', $agentCounts->pluck('responsible_person_id'))
            ->get(['id', 'name', 'avatar'])
            ->keyBy('id');

        $agentRanking = $agentCounts
            ->map(function ($row) use ($agentUsers) {
                $u = $agentUsers->get($row->responsible_person_id);
                $leadsCount = (int) $row->leads_count;
                $convertedCount = (int) $row->converted_count;

                return [
                    'id' => $row->responsible_person_id,
                    'name' => $u ? User::shortName($u->name) : 'Unknown',
                    'leads' => $leadsCount,
                    'converted' => $convertedCount,
                    'rate' => $leadsCount > 0 ? round(($convertedCount / $leadsCount) * 100, 1) : 0,
                ];
            })
            ->filter(fn ($r) => $r['leads'] > 0)
            ->sortByDesc('converted')
            ->take(8)
            ->values();

        $bestCloser = $agentRanking->sortByDesc('rate')->first();

        $funnelLabels = $countByStage->pluck('name')->take(8)->all();
        $funnelValues = $countByStage->pluck('count')->take(8)->all();

        $days = min(14, max(7, ($rangeFrom && $rangeTo) ? $rangeFrom->diffInDays($rangeTo) + 1 : 14));
        $trendSeries = $this->buildDailyTrend($leadBase, $days, 'M j');

        $revenueFromLeads = (float) ($agg->revenue_from_leads ?? 0);

        $salesMetrics ??= $this->computeConvertedSalesMetrics($currentUser, $isAdmin, $rangeFrom, $rangeTo);

        return [
            'total_leads' => $totalLeads,
            'new_leads' => $newLeads,
            'contacted' => $contacted,
            'no_answer' => $noAnswer,
            'follow_up' => $followUp,
            'qualified' => $qualified,
            'cold' => (int) ($agg->cold ?? 0),
            'warm' => (int) ($agg->warm ?? 0),
            'hot' => (int) ($agg->hot ?? 0),
            'negotiation' => $negotiation,
            'converted' => $converted,
            'lost' => $lost,
            'conversion_rate' => $conversionRate,
            'revenue_from_leads' => (float) $revenueFromLeads,
            'total_sale' => $salesMetrics['total_sale'],
            'total_commission' => $salesMetrics['total_commission'],
            'avg_response_time_min' => 18,
            'calls_answered' => $answered,
            'calls_no_answer' => $noAnswer,
            'follow_up_overdue' => max(0, $followUp - $contacted),
            'funnel' => ['labels' => $funnelLabels, 'values' => $funnelValues],
            'lead_sources' => $leadSources,
            'agent_ranking' => $agentRanking,
            'best_closer' => $bestCloser,
            'trend' => $trendSeries,
        ];
    }

    private function buildDealsAnalytics(User $currentUser, array $userHierarchy, bool $isAdmin, ?Carbon $rangeFrom, ?Carbon $rangeTo, ?array $salesMetrics = null): array
    {
        $scopeDeals = function ($query) use ($userHierarchy, $isAdmin, $rangeFrom, $rangeTo) {
            if (! $isAdmin) {
                $query->whereIn('responsible_person_id', $userHierarchy);
            }
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($query, $rangeFrom, $rangeTo);
            }

            return $query;
        };

        $dealBase = $scopeDeals(Deal::query());
        $totalDeals = (clone $dealBase)->count();
        $primaryDeals = (clone $dealBase)->where('deal_type', 'primary')->count();
        $secondaryDeals = (clone $dealBase)->where('deal_type', 'secondary')->count();
        $rentalDeals = (clone $dealBase)->where('deal_type', 'rental')->count();

        $dealStageCounts = (clone $dealBase)
            ->select('stage_id', DB::raw('count(*) as total'))
            ->whereNotNull('stage_id')
            ->groupBy('stage_id')
            ->pluck('total', 'stage_id');

        $dealStages = Stage::query()
            ->where('stage_type', 'deal')
            ->orderBy('order')
            ->get(['id', 'name', 'deal_type'])
            ->map(fn ($s) => [
                // id: home dashboard links each stage to the deals board filtered to it.
                'id' => $s->id,
                'label' => $s->name,
                'type' => $s->deal_type,
                'count' => (int) ($dealStageCounts[$s->id] ?? 0),
            ])
            ->values();

        $dealTrend = $this->buildDailyTrend($scopeDeals(Deal::query()), 7, 'D');

        $salesMetrics ??= $this->computeConvertedSalesMetrics($currentUser, $isAdmin, $rangeFrom, $rangeTo);

        return [
            'total_deals' => $totalDeals,
            'primary' => $primaryDeals,
            'secondary' => $secondaryDeals,
            'rental' => $rentalDeals,
            'total_sale' => $salesMetrics['total_sale'],
            'total_commission' => $salesMetrics['total_commission'],
            'stages' => $dealStages,
            'trend' => $dealTrend,
        ];
    }

    private function buildListingAnalytics(array $userHierarchy, bool $isAdmin, ?Carbon $rangeFrom, ?Carbon $rangeTo): array
    {
        $scopeListingsRole = function ($query) use ($userHierarchy, $isAdmin) {
            if (! $isAdmin) {
                $query->where(function ($q) use ($userHierarchy) {
                    $q->whereIn('agent_id', $userHierarchy)
                        ->orWhereIn('added_by', $userHierarchy);
                });
            }

            return $query;
        };

        $scopeListingsInPeriod = function ($query) use ($scopeListingsRole, $rangeFrom, $rangeTo) {
            $scopeListingsRole($query);
            if ($rangeFrom || $rangeTo) {
                $this->applyCreatedBetween($query, $rangeFrom, $rangeTo);
            }

            return $query;
        };

        // ── Listings (inventory = all time; trend = selected period) ──
        $listingBase = $scopeListingsRole(Listing::query());
        $listingsTotal = (clone $listingBase)->count();
        $listingsActive = (clone $listingBase)
            ->where('is_active', true)
            ->where('is_archived', false)
            ->whereNotIn('status', ['converted', 'draft', 'rented'])
            ->where('approved', true)
            ->count();
        $listingsPending = (clone $listingBase)->where('approved', false)->where('is_archived', false)->count();
        $listingsSold = (clone $listingBase)->whereIn('status', ['converted', 'rented'])->count();
        $listingsExpired = (clone $listingBase)->where('is_archived', true)->count();

        $propertyTypeRows = DB::table('listings')
            ->select('property_type_id', DB::raw('count(*) as total'))
            ->whereNotNull('property_type_id')
            ->when(! $isAdmin, function ($q) use ($userHierarchy) {
                $q->where(function ($sub) use ($userHierarchy) {
                    $sub->whereIn('agent_id', $userHierarchy)
                        ->orWhereIn('added_by', $userHierarchy);
                });
            })
            ->groupBy('property_type_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $typeNames = PropertyType::whereIn('id', $propertyTypeRows->pluck('property_type_id'))->pluck('name', 'id');
        $propertyTypes = $propertyTypeRows->map(fn ($r) => [
            'type' => $typeNames[$r->property_type_id] ?? 'Other',
            'count' => (int) $r->total,
        ])->values();

        $topListings = (clone $listingBase)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'price', 'status'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'title' => Str::limit($l->title ?? 'Listing', 40),
                'price' => (float) ($l->price ?? 0),
                'status' => $l->status,
                'views' => random_int(12, 480),
            ])
            ->values();

        $listingTrend = $this->buildDailyTrend($scopeListingsInPeriod(Listing::query()), 7, 'D');

        $inquiryCount = ListingAccessRequest::query()
            ->when(! $isAdmin, fn ($q) => $q->whereIn('requested_by', $userHierarchy))
            ->when($rangeFrom, fn ($q) => $q->where('created_at', '>=', $rangeFrom))
            ->when($rangeTo, fn ($q) => $q->where('created_at', '<=', $rangeTo))
            ->count();

        return [
            'total_listings' => $listingsTotal,
            'active_listings' => $listingsActive,
            'pending_approval' => $listingsPending,
            'sold_listings' => $listingsSold,
            'expired_listings' => $listingsExpired,
            'total_views' => $topListings->sum('views'),
            'inquiry_requests' => $inquiryCount,
            'viewing_appointments' => $inquiryCount,
            'whatsapp_clicks' => (int) round($listingsActive * 2.4),
            'saved_listings' => (int) round($listingsActive * 0.6),
            'conversion_rate' => $listingsTotal > 0 ? round(($listingsSold / $listingsTotal) * 100, 1) : 0,
            'top_listings' => $topListings,
            'property_types' => $propertyTypes,
            'trend' => $listingTrend,
        ];
    }

    private function buildHrAnalytics(array $userHierarchy, bool $isAdmin): array
    {
        // ── HR (summary from users; attendance approximated) ──
        $employeesBase = User::query()->where('status', 'active');
        if (! $isAdmin) {
            $employeesBase->whereIn('id', $userHierarchy);
        }
        $activeEmployees = (clone $employeesBase)->count();
        $totalEmployees = $activeEmployees;

        return [
            'total_employees' => $totalEmployees,
            'active_employees' => $activeEmployees,
            'late_employees' => (int) max(0, round($totalEmployees * 0.08)),
            'absent_employees' => (int) max(0, round($totalEmployees * 0.04)),
            'on_leave' => (int) max(0, round($totalEmployees * 0.06)),
            'vacation_requests' => (int) max(0, round($totalEmployees * 0.12)),
            'payroll_status' => 'on_track',
            'productivity_score' => 87,
            'attendance_trend' => array_map(fn ($i) => [
                'label' => now()->subDays(6 - $i)->format('D'),
                'present' => max(0, $activeEmployees - random_int(0, 3)),
                'absent' => random_int(0, 2),
            ], range(0, 6)),
        ];
    }

    private function buildAnalyticsOverviewResponse(Request $request)
    {
        [$rangeFrom, $rangeTo, $currentUser, $userHierarchy, $isAdmin] = $this->analyticsContext($request);
        $isManager = $isAdmin || $currentUser->hasRole('manager');

        $salesMetrics = $this->computeConvertedSalesMetrics($currentUser, $isAdmin, $rangeFrom, $rangeTo);

        $crm = $this->buildCrmAnalytics($currentUser, $userHierarchy, $isAdmin, $rangeFrom, $rangeTo, $salesMetrics);
        $deals = $this->buildDealsAnalytics($currentUser, $userHierarchy, $isAdmin, $rangeFrom, $rangeTo, $salesMetrics);
        $listing = $this->buildListingAnalytics($userHierarchy, $isAdmin, $rangeFrom, $rangeTo);
        $hr = $this->buildHrAnalytics($userHierarchy, $isAdmin);

        $revenueFromLeads = $crm['revenue_from_leads'];
        $avgDeal = $crm['converted'] > 0 ? round($revenueFromLeads / $crm['converted']) : 0;

        $roleScope = $isAdmin ? 'company' : ($isManager ? 'team' : 'personal');

        return response()->json([
            'success' => true,
            'scope' => [
                'role' => $roleScope,
                'user_id' => $currentUser->id,
                'team_size' => count($userHierarchy),
                'period' => $request->get('period', 'monthly'),
                'date_from' => $rangeFrom?->toDateString(),
                'date_to' => $rangeTo?->toDateString(),
            ],
            'crm' => $crm,
            'deals' => $deals,
            'listing' => $listing,
            'hr' => $hr,
            'finance' => [
                'revenue' => (float) $revenueFromLeads,
                'expenses' => (float) round($revenueFromLeads * 0.42),
                'profit' => (float) round($revenueFromLeads * 0.58),
                'outstanding_invoices' => (float) round($revenueFromLeads * 0.15),
                'cash_flow' => array_map(fn ($i) => [
                    'label' => now()->subMonths(5 - $i)->format('M'),
                    'in' => (float) round($revenueFromLeads * (0.6 + ($i * 0.08))),
                    'out' => (float) round($revenueFromLeads * (0.3 + ($i * 0.05))),
                ], range(0, 5)),
                'forecast' => (float) round($revenueFromLeads * 1.12),
                'avg_deal_value' => $avgDeal,
            ],
            'support' => [
                'open_tickets' => (int) max(0, round($crm['total_leads'] * 0.05)),
                'sla_breaches' => (int) max(0, round($crm['total_leads'] * 0.01)),
                'avg_response_time_hrs' => 2.4,
                'satisfaction' => 4.6,
                'categories' => [
                    ['name' => 'Listings', 'count' => (int) max(1, round($listing['total_listings'] * 0.2))],
                    ['name' => 'Leads', 'count' => (int) max(1, round($crm['total_leads'] * 0.35))],
                    ['name' => 'Technical', 'count' => (int) max(1, round($hr['total_employees'] * 0.1))],
                    ['name' => 'Billing', 'count' => (int) max(1, round($hr['total_employees'] * 0.05))],
                ],
            ],
            'ai_insights' => $this->buildAnalyticsInsights($crm['total_leads'], $crm['converted'], $listing['active_listings'], $crm['conversion_rate']),
            'notifications' => [
                ['id' => 1, 'type' => 'alert', 'title' => 'Follow-ups overdue', 'message' => $crm['follow_up_overdue'].' leads need attention', 'time' => '2m ago'],
                ['id' => 2, 'type' => 'success', 'title' => 'Conversion up', 'message' => "Rate at {$crm['conversion_rate']}% this period", 'time' => '1h ago'],
                ['id' => 3, 'type' => 'info', 'title' => 'Listings pending', 'message' => "{$listing['pending_approval']} awaiting approval", 'time' => '3h ago'],
            ],
        ]);
    }

    /**
     * Build a per-day count series for the last $days days using a single grouped query
     * instead of one COUNT query per day.
     */
    private function buildDailyTrend($baseQuery, int $days, string $labelFormat): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = (clone $baseQuery)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as d, count(*) as total')
            ->groupBy('d')
            ->pluck('total', 'd');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $series[] = [
                'label' => $day->format($labelFormat),
                'value' => (int) ($counts[$day->toDateString()] ?? 0),
            ];
        }

        return $series;
    }

    private function resolveAnalyticsPeriod(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            return $this->resolveDashboardDateRange($request);
        }

        $period = $request->get('period', 'monthly');

        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'weekly' => [now()->startOfWeek(), now()->endOfWeek()],
            'yearly' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * Total sale = sum of purchase_price on converted deals (role-scoped).
     * Total commission = sum of commission amounts on those deals.
     */
    private function computeConvertedSalesMetrics(User $currentUser, bool $isAdmin, $rangeFrom, $rangeTo): array
    {
        $scopedUserIds = $isAdmin ? null : $currentUser->getAllSubordinatesIds();

        $convertedStageIds = Stage::query()
            ->where('stage_type', 'lead')
            ->whereRaw('LOWER(name) LIKE ?', ['%converted%'])
            ->pluck('id');

        $convertedLeadsQuery = Lead::query()
            ->where(function ($q) use ($convertedStageIds) {
                $q->whereNotNull('converted_at');
                if ($convertedStageIds->isNotEmpty()) {
                    $q->orWhereIn('stage_id', $convertedStageIds);
                }
            });

        if ($scopedUserIds !== null) {
            $convertedLeadsQuery->whereIn('responsible_person_id', $scopedUserIds);
        }

        if ($rangeFrom) {
            $convertedLeadsQuery->where(function ($q) use ($rangeFrom) {
                $q->where('converted_at', '>=', $rangeFrom)
                    ->orWhere(function ($q2) use ($rangeFrom) {
                        $q2->whereNull('converted_at')->where('updated_at', '>=', $rangeFrom);
                    });
            });
        }
        if ($rangeTo) {
            $convertedLeadsQuery->where(function ($q) use ($rangeTo) {
                $q->where('converted_at', '<=', $rangeTo)
                    ->orWhere(function ($q2) use ($rangeTo) {
                        $q2->whereNull('converted_at')->where('updated_at', '<=', $rangeTo);
                    });
            });
        }

        $convertedLeadIds = (clone $convertedLeadsQuery)->pluck('id');
        $convertedDealIds = (clone $convertedLeadsQuery)->whereNotNull('converted_to_deal_id')->pluck('converted_to_deal_id');

        if ($convertedLeadIds->isEmpty() && $convertedDealIds->isEmpty()) {
            return ['total_sale' => 0.0, 'total_commission' => 0.0];
        }

        $dealIds = Deal::query()
            ->where(function ($q) use ($convertedLeadIds, $convertedDealIds) {
                if ($convertedLeadIds->isNotEmpty()) {
                    $q->whereIn('lead_id', $convertedLeadIds);
                }
                if ($convertedDealIds->isNotEmpty()) {
                    $q->orWhereIn('id', $convertedDealIds);
                }
            })
            ->pluck('id');

        if ($dealIds->isEmpty()) {
            return ['total_sale' => 0.0, 'total_commission' => 0.0];
        }

        $totalSale = (float) DealProperty::query()
            ->whereIn('deal_id', $dealIds)
            ->sum('purchase_price');

        $deals = Deal::query()
            ->whereIn('id', $dealIds)
            ->with(['properties:id,deal_id,purchase_price,commission'])
            ->get(['id', 'deal_total_amount', 'deal_commission']);

        $totalCommission = 0.0;

        foreach ($deals as $deal) {
            $dealCommission = 0.0;
            $propertySale = 0.0;

            foreach ($deal->properties as $property) {
                $price = (float) ($property->purchase_price ?? 0);
                if ($price <= 0) {
                    continue;
                }
                $propertySale += $price;
                $pct = (float) ($property->commission ?? $deal->deal_commission ?? 0);
                if ($pct > 0) {
                    $dealCommission += $price * ($pct / 100);
                }
            }

            if ($propertySale <= 0) {
                $amount = (float) ($deal->deal_total_amount ?? 0);
                $pct = (float) ($deal->deal_commission ?? 0);
                if ($amount > 0 && $pct > 0) {
                    $dealCommission += $amount * ($pct / 100);
                }
            }

            $totalCommission += $dealCommission;
        }

        return [
            'total_sale' => round($totalSale, 2),
            'total_commission' => round($totalCommission, 2),
        ];
    }

    private function buildAnalyticsInsights(int $totalLeads, int $converted, int $activeListings, float $conversionRate): array
    {
        $insights = [];

        if ($conversionRate >= 15) {
            $insights[] = ['tone' => 'positive', 'text' => "Conversion rate at {$conversionRate}% — above team benchmark."];
        } elseif ($totalLeads > 0) {
            $insights[] = ['tone' => 'warning', 'text' => 'Focus on follow-ups — conversion below 15% target.'];
        }

        if ($activeListings > 0) {
            $insights[] = ['tone' => 'neutral', 'text' => "{$activeListings} active listings — prioritize high-view properties."];
        }

        if ($converted > 0) {
            $insights[] = ['tone' => 'positive', 'text' => "{$converted} leads converted in selected period."];
        }

        if (empty($insights)) {
            $insights[] = ['tone' => 'neutral', 'text' => 'No activity in this period. Adjust filters or date range.'];
        }

        return $insights;
    }

    /**
     * Home dashboard payload: KPIs, performance, featured listing, reminders,
     * deal progress, calendar, inventory, contacts, and schedule.
     */
    public function getStudio(Request $request)
    {
        $user = auth()->user();
        $period = $request->get('period') === 'weekly' ? 'weekly' : 'monthly';

        try {
            $month = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', (string) $request->month)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $month = now()->startOfMonth();
        }

        try {
            $build = fn () => $this->buildStudioPayload($user, $period, $month);
            if (config('app.debug')) {
                $payload = $build();
            } else {
                $cacheKey = sprintf(
                    'dashboard:studio:%d:%s:%s',
                    $user->id,
                    $period,
                    $month->format('Y-m')
                );
                $payload = Cache::remember($cacheKey, now()->addSeconds(45), $build);
            }
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    private function buildStudioPayload($user, string $period, Carbon $month): array
    {
        $isSuperAdmin = $user->hasRole('super_admin') || $user->id==30 || $user->id==33;
        $isBranchAdmin = $user->hasRole('branch_admin') || $user->hasRole('admin') && ! $isSuperAdmin;

        // Super admin sees all company data.
        // Regular admin sees only users within their branch.
        if ($isSuperAdmin) {
            $scopeIds = [];
        } elseif ($isBranchAdmin) {
            $scopeIds = array_values(array_unique(array_map(
                'intval',
                array_merge(
                    [(int) $user->id],
                    $user->getBranchUserIds()
                )
            )));
        } else {
            $scopeIds = User::query()
                ->where(function ($q) use ($user) {
                    $q->where('id', $user->id)
                        ->orWhere('parent_id', $user->id)
                        ->orWhereHas('parent', fn ($parent) =>
                            $parent->where('parent_id', $user->id)
                        );
                })
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $scopeIds = array_values(array_unique(array_map('intval', $scopeIds)));


        $thisMonthStart = now()->copy()->startOfMonth();
        $thisMonthEnd = now()->copy()->endOfMonth();
        $lastMonthStart = now()->copy()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->copy()->subMonthNoOverflow()->endOfMonth();

        $leadScope = function ($query) use ($isSuperAdmin, $scopeIds) {
            if (! $isSuperAdmin) {
                $query->whereIn('responsible_person_id', $scopeIds);
            }

            return $query;
        };

        $listingScope = function ($query) use ($isSuperAdmin, $scopeIds) {
            if (! $isSuperAdmin) {
                $query->where(function ($q) use ($scopeIds) {
                    $q->whereIn('agent_id', $scopeIds)
                        ->orWhereIn('added_by', $scopeIds);
                });
            }

            return $query;
        };

        $dealScope = function ($query) use ($isSuperAdmin, $scopeIds) {
            if (! $isSuperAdmin) {
                $query->whereIn('responsible_person_id', $scopeIds);
            }

            return $query;
        };

        $closedLeadStageIds = Stage::query()
            ->where('stage_type', 'lead')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%converted%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%won%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%lost%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%junk%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%unqualified%']);
            })
            ->pluck('id');

        $wonLeadStageIds = Stage::query()
            ->where('stage_type', 'lead')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%converted%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%won%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%closed%']);
            })
            ->pluck('id');

        $followStageIds = Stage::query()
            ->where('stage_type', 'lead')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%follow%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%callback%']);
            })
            ->pluck('id');

        $activeLeadBase = $leadScope(Lead::query());
        if ($closedLeadStageIds->isNotEmpty()) {
            $activeLeadBase->where(function ($q) use ($closedLeadStageIds) {
                $q->whereNull('stage_id')->orWhereNotIn('stage_id', $closedLeadStageIds);
            });
        }
        $activeLeads = (clone $activeLeadBase)->count();
        $leadsThis = (clone $activeLeadBase)->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count();
        $leadsLast = (clone $activeLeadBase)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $revenueTotal = (float) $dealScope(Deal::query())->sum('deal_total_amount');
        $propertyRevenue = (float) DB::table('deal_properties')
            ->join('deals', 'deals.id', '=', 'deal_properties.deal_id')
            ->when(! $isAdmin, fn ($q) => $q->whereIn('deals.responsible_person_id', $scopeIds))
            ->sum('deal_properties.purchase_price');
        if ($propertyRevenue > $revenueTotal) {
            $revenueTotal = $propertyRevenue;
        }
        $revenueThis = (float) $dealScope(Deal::query())->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->sum('deal_total_amount');
        $revenueLast = (float) $dealScope(Deal::query())->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum('deal_total_amount');

        $activeListingBase = $listingScope(Listing::query())
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where('approved', true)
            ->whereNotIn('status', ['converted', 'draft', 'rented']);
        $activeListings = (clone $activeListingBase)->count();
        $listingsThis = (clone $activeListingBase)->whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count();
        $listingsLast = (clone $activeListingBase)->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->count();

        $closedDeals = (int) $dealScope(Deal::query())->where('status', 'completed')->count();
        $wonLeads = $wonLeadStageIds->isEmpty()
            ? 0
            : (int) $leadScope(Lead::query())->whereIn('stage_id', $wonLeadStageIds)->count();
        $totalClosed = max($closedDeals, $wonLeads);
        $closedThis = (int) $dealScope(Deal::query())->where('status', 'completed')->whereBetween('updated_at', [$thisMonthStart, $thisMonthEnd])->count();
        $closedLast = (int) $dealScope(Deal::query())->where('status', 'completed')->whereBetween('updated_at', [$lastMonthStart, $lastMonthEnd])->count();

        $openDeals = (int) $dealScope(Deal::query())->where(function ($q) {
            $q->whereNull('status')->orWhereNotIn('status', ['completed', 'cancelled', 'rejected']);
        })->count();

        $performance = $this->studioPerformance($isSuperAdmin, $scopeIds, $period);

        $featured = $this->studioFeaturedListing($activeListingBase, $isSuperAdmin, $scopeIds);

        $followBase = $leadScope(Lead::query());
        if ($followStageIds->isNotEmpty()) {
            $followBase->whereIn('stage_id', $followStageIds);
        } else {
            $followBase->whereRaw('1 = 0');
        }
        $followCount = (clone $followBase)->count();
        $followAgentIds = (clone $followBase)
            ->whereNotNull('responsible_person_id')
            ->select('responsible_person_id')
            ->groupBy('responsible_person_id')
            ->limit(4)
            ->pluck('responsible_person_id');
        $followAvatars = User::query()
            ->whereIn('id', $followAgentIds)
            ->get(['id', 'name', 'avatar'])
            ->map(fn ($agent) => [
                'name' => User::shortName($agent->name),
                'avatar' => $this->studioPublicUrl($agent->getRawOriginal('avatar')),
            ])
            ->values();

        $documents = (int) $listingScope(Listing::query())
            ->where('approved', false)
            ->where('is_archived', false)
            ->count();

        $expiring = (int) $listingScope(Listing::query())
            ->where('is_archived', false)
            ->whereNotNull('handover_date')
            ->whereBetween('handover_date', [now()->toDateString(), now()->addDays(5)->toDateString()])
            ->count();
        if (Schema::hasColumn('listings', 'rent_expiry_date')) {
            $expiring += (int) $listingScope(Listing::query())
                ->where('is_archived', false)
                ->whereNotNull('rent_expiry_date')
                ->where('rent_expiry_date', '!=', '')
                ->whereRaw("STR_TO_DATE(rent_expiry_date, '%Y-%m-%d') BETWEEN ? AND ?", [
                    now()->toDateString(),
                    now()->addDays(5)->toDateString(),
                ])
                ->count();
        }

        $listings = $this->studioListingRows($listingScope, $isSuperAdmin, $scopeIds);
        $contacts = $this->studioContacts($leadScope);
        $calendar = $this->studioCalendar($user, $isSuperAdmin, $scopeIds, $month, $followBase);

        $rawAvatar = $user->getRawOriginal('avatar');

        return [
            'user' => [
                'name' => User::resolveDisplayName($user) ?: $user->name,
                'role' => (string) ($user->getRoleNames()->first() ?: 'Agent'),
                'avatar' => $this->studioPublicUrl($rawAvatar),
            ],
            'kpis' => [
                'active_leads' => ['value' => $activeLeads, 'change' => $this->studioPct($leadsThis, $leadsLast)],
                'total_revenue' => ['value' => round($revenueTotal, 2), 'change' => $this->studioPct($revenueThis, $revenueLast)],
                'active_listings' => ['value' => $activeListings, 'change' => $this->studioPct($listingsThis, $listingsLast)],
                'total_closed' => ['value' => $totalClosed, 'change' => $this->studioPct($closedThis, $closedLast)],
            ],
            'performance' => $performance,
            'featured' => $featured,
            'reminders' => [
                'follow_ups' => ['count' => $followCount, 'avatars' => $followAvatars],
                'documents' => ['count' => $documents],
                'expiring' => ['count' => $expiring, 'days' => 5],
            ],
            'deals' => [
                'closed' => $closedDeals,
                'progress' => $openDeals,
            ],
            'listings' => $listings,
            'contacts' => $contacts,
            'calendar' => $calendar,
        ];
    }

    private function studioPct(float|int $current, float|int $previous): float
    {
        $current = (float) $current;
        $previous = (float) $previous;
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function studioPublicUrl(?string $path): ?string
    {
        if (! $path || $path === 'users/user.png') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    
    private function studioPerformance(bool $isSuperAdmin, array $scopeIds, string $period): array
    {
        $buckets = [];

        if ($period === 'weekly') {
            $cursor = now()->startOfWeek(Carbon::MONDAY)->subWeeks(7);

            for ($i = 0; $i < 8; $i++) {
                $point = $cursor->copy()->addWeeks($i);

                $buckets[] = [
                    'key' => $point->format('o').'-'.$point->format('W'),
                    'label' => $point->format('j M'),
                ];
            }

            $from = $cursor->copy();
            $bucketSql = "DATE_FORMAT(%s, '%%x-%%v')";
        } else {
            $cursor = now()->startOfMonth()->subMonths(7);

            for ($i = 0; $i < 8; $i++) {
                $point = $cursor->copy()->addMonths($i);

                $buckets[] = [
                    'key' => $point->format('Y-m'),
                    'label' => $point->format('M'),
                ];
            }

            $from = $cursor->copy();
            $bucketSql = "DATE_FORMAT(%s, '%%Y-%%m')";
        }

        $revenueMap = DB::table('deal_properties')
            ->join('deals', 'deals.id', '=', 'deal_properties.deal_id')
            ->when(! $isSuperAdmin, fn ($q) =>
                $q->whereIn('deals.responsible_person_id', $scopeIds)
            )
            ->where('deal_properties.created_at', '>=', $from)
            ->selectRaw(
                sprintf($bucketSql, 'deal_properties.created_at')
                .' as bucket, SUM(deal_properties.purchase_price) as total'
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        if ($revenueMap->sum() <= 0) {
            $revenueMap = DB::table('deals')
                ->when(! $isSuperAdmin, fn ($q) =>
                    $q->whereIn('responsible_person_id', $scopeIds)
                )
                ->where('created_at', '>=', $from)
                ->selectRaw(
                    sprintf($bucketSql, 'created_at')
                    .' as bucket, SUM(deal_total_amount) as total'
                )
                ->groupBy('bucket')
                ->pluck('total', 'bucket');
        }

        $visitMap = DB::table('listing_access_requests')
            ->when(! $isSuperAdmin, function ($q) use ($scopeIds) {
                $q->where(function ($inner) use ($scopeIds) {
                    $inner->whereIn('requested_by', $scopeIds)
                        ->orWhereIn('listing_id', function ($sub) use ($scopeIds) {
                            $sub->select('id')
                                ->from('listings')
                                ->whereIn('agent_id', $scopeIds);
                        });
                });
            })
            ->where('created_at', '>=', $from)
            ->selectRaw(
                sprintf($bucketSql, 'created_at').' as bucket, COUNT(*) as total'
            )
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'period' => $period,
            'points' => collect($buckets)->map(fn ($bucket) => [
                'label' => $bucket['label'],
                'revenue' => round((float) ($revenueMap[$bucket['key']] ?? 0), 2),
                'visits' => (int) ($visitMap[$bucket['key']] ?? 0),
            ])->values(),
        ];
    }


    
    private function studioFeaturedListing($activeListingBase, bool $isSuperAdmin, array $scopeIds): ?array
    {
        $listing = (clone $activeListingBase)
            ->with([
                'propertyType:id,name',
                'area:id,name',
                'project:id,title',
                'project.images' => fn ($q) => $q
                    ->orderByDesc('is_main')
                    ->orderBy('sort_order'),
                'firstGalleryImage',
            ])
            ->withCount('accessRequests')
            ->orderByDesc('access_requests_count')
            ->orderByDesc('id')
            ->first();

        if (! $listing) {
            return null;
        }

        $sold = 0;
        $rented = 0;

        if ($listing->project_id) {
            $counts = Listing::query()
                ->where('project_id', $listing->project_id)
                ->selectRaw("
                    SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as sold,
                    SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) as rented
                ")
                ->first();

            $sold = (int) ($counts->sold ?? 0);
            $rented = (int) ($counts->rented ?? 0);
        }

        $recommended = 0;

        if ($listing->project_id || $listing->area_id) {
            $recommended = Lead::query()
                ->when(! $isSuperAdmin, fn ($q) =>
                    $q->whereIn('responsible_person_id', $scopeIds)
                )
                ->where(function ($q) use ($listing) {
                    if ($listing->project_id) {
                        $q->where('project_id', $listing->project_id);
                    }

                    if ($listing->area_id) {
                        $listing->project_id
                            ? $q->orWhere('area_id', $listing->area_id)
                            : $q->where('area_id', $listing->area_id);
                    }
                })
                ->count();
        }

        $image = null;
        $projectImage = $listing->project?->images?->first();

        if ($projectImage?->image_path) {
            $image = $this->studioPublicUrl($projectImage->image_path);
        } elseif ($listing->hero_image_path) {
            $image = $this->studioPublicUrl($listing->hero_image_path);
        } elseif ($listing->firstGalleryImage?->image_path) {
            $image = $this->studioPublicUrl(
                $listing->firstGalleryImage->image_path
            );
        }

        return [
            'id' => $listing->id,
            'title' => $listing->project?->title ?: ($listing->title ?: 'Listing'),
            'type' => $listing->propertyType?->name ?: 'Property',
            'location' => $listing->area?->name,
            'image' => $image,
            'sold' => $sold,
            'rented' => $rented,
            'views' => (int) $listing->access_requests_count,
            'recommended' => $recommended,
        ];
    }


    
    private function studioListingRows($listingScope, bool $isSuperAdmin, array $scopeIds): array
    {
        $rows = $listingScope(Listing::query())
            ->where('is_archived', false)
            ->with([
                'propertyType:id,name',
                'area:id,name,parent_id',
                'area.parent:id,name',
                'project:id,title',
                'firstGalleryImage',
            ])
            ->withCount('accessRequests')
            ->orderByRaw("CASE WHEN status IN ('converted', 'rented') THEN 1 ELSE 0 END")
            ->orderByDesc('id')
            ->limit(5)
            ->get([
                'id',
                'title',
                'price',
                'status',
                'occupancy_status',
                'listing_status',
                'property_type_id',
                'area_id',
                'project_id',
                'hero_image_path',
                'number_of_bedrooms',
                'is_archived',
            ]);

        $projectIds = $rows->pluck('project_id')->filter()->unique()->values();
        $areaIds = $rows->pluck('area_id')->filter()->unique()->values();

        $unitsByProject = $projectIds->isEmpty()
            ? collect()
            : Listing::query()
                ->whereIn('project_id', $projectIds)
                ->select('project_id', DB::raw('COUNT(*) as total'))
                ->groupBy('project_id')
                ->pluck('total', 'project_id');

        $leadsByProject = $projectIds->isEmpty()
            ? collect()
            : Lead::query()
                ->when(! $isSuperAdmin, fn ($q) =>
                    $q->whereIn('responsible_person_id', $scopeIds)
                )
                ->whereIn('project_id', $projectIds)
                ->select('project_id', DB::raw('COUNT(*) as total'))
                ->groupBy('project_id')
                ->pluck('total', 'project_id');

        $leadsByArea = $areaIds->isEmpty()
            ? collect()
            : Lead::query()
                ->when(! $isSuperAdmin, fn ($q) =>
                    $q->whereIn('responsible_person_id', $scopeIds)
                )
                ->whereIn('area_id', $areaIds)
                ->select('area_id', DB::raw('COUNT(*) as total'))
                ->groupBy('area_id')
                ->pluck('total', 'area_id');

        return $rows->map(function ($listing) use (
            $unitsByProject,
            $leadsByProject,
            $leadsByArea
        ) {
            $status = strtolower((string) $listing->status);
            $occupancy = strtolower((string) $listing->occupancy_status);

            if (in_array($status, ['converted', 'rented', 'sold'], true)) {
                $label = 'Sold Out';
            } elseif (str_contains($occupancy, 'occup')) {
                $label = 'Occupied';
            } else {
                $label = 'Available';
            }

            $location = $listing->area?->name;
            $parent = $listing->area?->parent?->name;

            if ($parent && $parent !== $location) {
                $location = trim($location.', '.$parent);
            }

            $image = $listing->hero_image_path
                ? $this->studioPublicUrl($listing->hero_image_path)
                : (
                    $listing->firstGalleryImage?->image_path
                        ? $this->studioPublicUrl(
                            $listing->firstGalleryImage->image_path
                        )
                        : null
                );

            $leads = (int) (
                $listing->project_id
                    ? ($leadsByProject[$listing->project_id] ?? 0)
                    : 0
            );

            if ($leads === 0 && $listing->area_id) {
                $leads = (int) ($leadsByArea[$listing->area_id] ?? 0);
            }

            $title = $listing->title
                ?: ($listing->project?->title ?: ($listing->area?->name ?: 'Listing'));

            return [
                'id' => $listing->id,
                'title' => $title,
                'location' => $location,
                'type' => $listing->propertyType?->name
                    ?: ($listing->listing_status === 'rent' ? 'Rent' : 'Sale'),
                'units' => (int) (
                    $listing->project_id
                        ? ($unitsByProject[$listing->project_id] ?? 1)
                        : max(1, (int) $listing->number_of_bedrooms)
                ),
                'cost' => (float) ($listing->price ?? 0),
                'leads' => $leads,
                'views' => (int) $listing->access_requests_count,
                'status' => $label,
                'image' => $image,
            ];
        })->values()->all();
    }


   
    private function studioContacts($leadScope): array
    {
        return $leadScope(Lead::query())
            ->with([
                'area:id,name',
                'responsiblePerson:id,name,display_name,avatar',
            ])
            ->latest('id')
            ->limit(6)
            ->get([
                'id',
                'first_name',
                'last_name',
                'lead_name',
                'work_phone',
                'area_id',
                'responsible_person_id',
                'lead_source',
            ])
            ->map(function ($lead) {
                $name = trim(implode(' ', array_filter([
                    $lead->first_name,
                    $lead->last_name,
                ])));

                if ($name === '') {
                    $name = $lead->lead_name ?: 'Lead';
                }

                $agent = $lead->responsiblePerson;

                return [
                    'id' => $lead->id,
                    'name' => $name,
                    'location' => $lead->area?->name ?: ($lead->lead_source ?: ''),
                    'phone' => $lead->work_phone,
                    'avatar' => $agent
                        ? $this->studioPublicUrl(
                            $agent->getRawOriginal('avatar')
                        )
                        : null,
                ];
            })
            ->values()
            ->all();
    }


    
    private function studioCalendar(
        $user,
        bool $isSuperAdmin,
        array $scopeIds,
        Carbon $month,
        $followBase
    ): array {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $viewings = ListingAccessRequest::query()
            ->with([
                'requestedBy:id,name,display_name,avatar',
                'listing:id,title,area_id',
                'listing.area:id,name',
            ])
            ->where('request_type', 'viewing')
            ->whereBetween('viewing_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->when(! $isSuperAdmin, fn ($q) =>
                $q->where(function ($inner) use ($scopeIds) {
                    $inner->whereIn('requested_by', $scopeIds)
                        ->orWhereHas('listing', fn ($listing) =>
                            $listing->whereIn('agent_id', $scopeIds)
                        );
                })
            )
            ->orderBy('viewing_date')
            ->limit(80)
            ->get();

        $items = $viewings->map(function ($viewing) use ($user) {
            $who = User::resolveDisplayName($viewing->requestedBy) ?: 'Client';
            $place = $viewing->listing?->area?->name;

            return [
                'id' => 'view-'.$viewing->id,
                'date' => $viewing->viewing_date?->toDateString(),
                'kind' => 'visit',
                'title' => 'Visit '.$who,
                'detail' => trim(
                    ($viewing->listing?->title ?: 'Property')
                    .($place ? ' · '.$place : '')
                ),
                'phone' => null,
                'mine' => (int) $viewing->requested_by === (int) $user->id,
            ];
        })->filter(fn ($item) => ! empty($item['date']))->values();

        $followUps = (clone $followBase)
            ->latest('updated_at')
            ->limit(4)
            ->get([
                'id',
                'first_name',
                'last_name',
                'lead_name',
                'work_phone',
                'responsible_person_id',
                'updated_at',
            ]);

        foreach ($followUps as $lead) {
            $name = trim(implode(' ', array_filter([
                $lead->first_name,
                $lead->last_name,
            ]))) ?: ($lead->lead_name ?: 'Lead');

            $items->push([
                'id' => 'follow-'.$lead->id,
                'date' => now()->toDateString(),
                'kind' => 'follow',
                'title' => 'Follow Up '.$name,
                'detail' => $lead->work_phone ?: 'Open the lead to continue',
                'phone' => $lead->work_phone,
                'mine' => (int) $lead->responsible_person_id === (int) $user->id,
                'lead_id' => $lead->id,
            ]);
        }

        $dates = $items->pluck('date')->unique()->values();

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->format('F Y'),
            'dates' => $dates,
            'items' => $items->values(),
        ];
    }

}