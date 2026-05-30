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
use Spatie\Activitylog\Models\Activity;
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
}
    public function getStats(Request $request)
    {
        [$rangeFrom, $rangeTo] = $this->resolveDashboardDateRange($request);
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $changeFrom = $rangeFrom ?? $thirtyDaysAgo;
        $changeTo = $rangeTo;
        $currentUser=auth()->user();
        $user_herarchy=User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            })->pluck('id')->toArray();
        // Total Agents (users with sales role)
        $agentsBase = User::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_herarchy){
            $q->whereIn('parent_id', $user_herarchy);
        });
        $totalAgents = (clone $agentsBase)->when($rangeFrom || $rangeTo, function ($q) use ($rangeFrom, $rangeTo) {
            $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
        })->count();

        $agentsChange = (clone $agentsBase)->where('created_at', '>=', $changeFrom)
            ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
            ->count();

        // Total Listings
        $totalListings = Listing::where('is_active',true)->where('is_archived',false)->whereNotIn('status',['converted','draft'])->count();
        $listingsChange = Listing::where('is_active',true)->where('is_archived',false)->whereNotIn('status',['converted','draft'])->where('created_at', '>=', $thirtyDaysAgo)->count();

        // My Orders (for authenticated user)
        $ordersBase = ListingAccessRequest::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_herarchy){
            $q->where('requested_by', auth()->id());
        });
        $myOrders = (clone $ordersBase)->when($rangeFrom || $rangeTo, function ($q) use ($rangeFrom, $rangeTo) {
            $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
        })->count();
        $ordersChange = (clone $ordersBase)->where('created_at', '>=', $changeFrom)
            ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
            ->count();

        $requestsBase = ListingAccessRequest::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_herarchy){
             $q->whereHas('listing', function ($query) use ($user_herarchy) {
                $query->where(function($q) use ($user_herarchy) {
                $q->orWhereIn('agent_id', $user_herarchy);
            });
        });});
        $myRequests = (clone $requestsBase)->when($rangeFrom || $rangeTo, function ($q) use ($rangeFrom, $rangeTo) {
            $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
        })->count();
        $requestsChange = (clone $requestsBase)->where('created_at', '>=', $changeFrom)
            ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
            ->count();

         $developers=Developer::count();
         $ownersBase = Owner::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_herarchy){
            $q->where('added_by', auth()->id());
        });
         $owners = (clone $ownersBase)->when($rangeFrom || $rangeTo, function ($q) use ($rangeFrom, $rangeTo) {
            $this->applyCreatedBetween($q, $rangeFrom, $rangeTo);
        })->count();
        $ownersChange = (clone $ownersBase)->where('created_at', '>=', $changeFrom)
            ->when($changeTo, fn ($q) => $q->where('created_at', '<=', $changeTo))
            ->count();
         $property_types=PropertyType::count();
         $unit_views=UnitView::count();
         $areas=Area::count();
         $layout_types=LayoutType::count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_agents' => $totalAgents,
                'agents_change' => $agentsChange,
                'total_listings' => $totalListings,
                'listings_change' => $listingsChange,
                'my_orders' => $myOrders,
                'orders_change' => $ordersChange,
                'my_requests' => $myRequests,
                'requests_change' => $requestsChange,
                'owners'=>$owners,
                'owners_change' => $ownersChange,
                'developers'=>$developers,
                'property_types'=>$property_types,
                'unit_views'=>$unit_views,
                'areas'=>$areas,
                'layout_types'=>$layout_types,
               
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
        ->get(['id', 'name']);

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
            'agent_name' => $agent->name,
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
                            'name' => $request->listing->agent->name ?? 'N/A',
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
                'name' => $request->requestedBy->name ?? 'N/A',
                'email' => $request->requestedBy->email ?? 'N/A',
                'avatar' => $request->requestedBy->avatar ? asset('storage/'. $request->requestedBy->avatar) : 'N/A',
          
            ],
            'request_to' => [
                'name' => $request->listing->agent->name ?? 'N/A',
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
            'name' => $user->name,
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
        'requestedBy:id,name,email,avatar',
        'listing:id,title,agent_id,property_type_id,area_id', 
        'listing:id,title,agent_id,property_type_id',
        'listing.agent:id,name,email,avatar',
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
                'name' => $request->requestedBy->name ?? 'N/A',
                'email' => $request->requestedBy->email ?? 'N/A',
                                'avatar' => $request->requestedBy->avatar ? asset('storage/'. $request->requestedBy->avatar) : 'N/A',

            ],
            'request_to' => [
                'name' => $request->listing->agent->name ?? 'N/A',
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
            foreach ($stages as $stage) {
                $name = strtolower((string) $stage->name);
                foreach ($needles as $needle) {
                    if (str_contains($name, $needle)) {
                        return (int) $stage->leads_count;
                    }
                }
            }

            return 0;
        };

        return response()->json([
            'success' => true,
            'data' => [
                'new' => $pick(['new', 'fresh', 'incoming']),
                'assigned' => $pick(['assign', 'contact', 'qualified']),
                'deal_won' => $pick(['won', 'closed', 'convert', 'deal']),
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

        $orders = ListingAccessRequest::with(['requestedBy:id,name,avatar', 'listing:id,title'])
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
                        'name' => $item->requestedBy?->name,
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
            $currentUser=$user;
              $user_hierarchy = User::where(function($q) use ($currentUser) {
                $q->where('id', $currentUser->id)
                ->orWhere('parent_id', $currentUser->id)
                ->orWhereHas('parent', function($parentQuery) use ($currentUser) {
                    $parentQuery->where('parent_id', $currentUser->id);
                });
            })->pluck('id')->toArray();

            $requests = ListingAccessRequest::when(!($currentUser->hasRole('super_admin')|| $currentUser->hasRole('admin')) ,function($q)use ($user_hierarchy,$user){
             $q->whereHas('listing', function ($query) use ($user_hierarchy) {
                $query->where(function($q) use ($user_hierarchy) {
                $q->orWhereIn('agent_id', $user_hierarchy);
            });
        })->orWhere('handled_by', $user->id);
        })
                ->count();

            $orders = ListingAccessRequest::with(['listing', 'requestedBy','convertedBy'])
            
                ->when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('requested_by', $user_hierarchy);
                })
                ->orderBy('created_at', 'desc')
                ->count();
            $hot_deals = HotDealRequest::with(['listing', 'requester'])
              ->when(!($user->hasRole('admin') || $user->hasRole('super_admin')), function($q) use ($user_hierarchy) {
                    $q->whereIn('requested_by', $user_hierarchy);
                })  ->where('status', 'pending')->orderBy('created_at', 'desc')
                ->count();
            $needApprove=Listing::where('approved', false)
                            ->where('status', 'published')
                            ->where('is_archived', false)->count();
            $counts = [
                'listings' => [
                    'all' => Listing::where('is_active',true)->where('is_archived',false)->whereNotIn('status',['converted','draft','rented']) 
                ->where('approved', true)->count(),
                    'my' => Listing::whereIn('agent_id', $user_hierarchy)->count(),
                    'archive' => Listing::where('status', 'archived')->count()
                ],
                'requests' => [
                    'all' =>$requests,
                ],
                'orders' => [
                    'all' => $orders,
                ],
                'hot_deals' => [
                    'all' => $hot_deals,
                ],
                'needapprove' => [
                    'all' => $needApprove,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $counts
            ]);

        } catch (\Exception $e) {
            dd($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sidebar counts'
            ], 500);
        }
    }
    
}