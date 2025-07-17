<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Environment;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Invoice;
use App\Models\Template;
use App\Models\Course;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WeeklyAnalyticsReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:weekly-report {--email : Send report via email} {--week= : Specific week to report (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send weekly analytics report with key metrics';

    /**
     * Email recipients for the report
     *
     * @var array
     */
    protected $recipients = [
        'kevinliboire@gmail.com',
        'data.analyst@cfpcsl.com',
        'romeo.ngangnang@cfpcsl.com',
        'direction@cfpcsl.com'
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting weekly analytics report generation...');

        try {
            // Determine the week for the report
            $weekStart = $this->option('week') 
                ? Carbon::parse($this->option('week'))->startOfWeek()
                : Carbon::now()->subWeek()->startOfWeek();
            
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $this->info("Generating report for week: {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");

            // Collect all metrics
            $metrics = $this->collectMetrics($weekStart, $weekEnd);
            
            // Generate report content
            $reportContent = $this->generateReportContent($metrics, $weekStart, $weekEnd);
            
            // Display report in console
            $this->displayReport($metrics, $weekStart, $weekEnd);
            
            // Send email if requested
            if ($this->option('email')) {
                $this->sendEmailReport($reportContent, $weekStart, $weekEnd);
                $this->info('Weekly analytics report email sent successfully');
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Report generation failed: " . $e->getMessage());
            Log::error('Weekly analytics report failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Collect all metrics for the report
     */
    protected function collectMetrics(Carbon $weekStart, Carbon $weekEnd): array
    {
        $this->info('Collecting metrics...');
        
        return [
            'new_registrations' => $this->getNewRegistrations($weekStart, $weekEnd),
            'learning_environments' => $this->getLearningEnvironments($weekStart, $weekEnd),
            'completed_orders' => $this->getCompletedOrders($weekStart, $weekEnd),
            'failed_orders' => $this->getFailedOrders($weekStart, $weekEnd),
            'total_commissions' => $this->getTotalCommissions($weekStart, $weekEnd),
            'pending_invoices' => $this->getPendingInvoices($weekStart, $weekEnd),
            'published_templates' => $this->getPublishedTemplates($weekStart, $weekEnd),
            'published_courses' => $this->getPublishedCourses($weekStart, $weekEnd),
            'new_enrollments' => $this->getNewEnrollments($weekStart, $weekEnd),
            'active_users' => $this->getActiveUsers($weekStart, $weekEnd),
            'revenue_breakdown' => $this->getRevenueBreakdown($weekStart, $weekEnd),
            'top_environments' => $this->getTopEnvironments($weekStart, $weekEnd),
        ];
    }

    /**
     * Get new user registrations for the week
     */
    protected function getNewRegistrations(Carbon $weekStart, Carbon $weekEnd): array
    {
        $newUsers = User::whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('COUNT(*) as total, role')
            ->groupBy('role')
            ->get();

        $total = User::whereBetween('created_at', [$weekStart, $weekEnd])->count();

        // Convert enum roles to string values for array keys
        $byRole = [];
        foreach ($newUsers as $user) {
            $roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role;
            $byRole[$roleValue] = $user->total;
        }

        return [
            'total' => $total,
            'by_role' => $byRole,
        ];
    }

    /**
     * Get learning environments data
     */
    protected function getLearningEnvironments(Carbon $weekStart, Carbon $weekEnd): array
    {
        $newEnvironments = Environment::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $totalEnvironments = Environment::count();
        $activeEnvironments = Environment::where('is_active', true)->count();

        return [
            'new_this_week' => $newEnvironments,
            'total' => $totalEnvironments,
            'active' => $activeEnvironments,
        ];
    }

    /**
     * Get completed orders data
     */
    protected function getCompletedOrders(Carbon $weekStart, Carbon $weekEnd): array
    {
        $completedOrders = Order::where('status', Order::STATUS_COMPLETED)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();

        $totalAmount = $completedOrders->sum('total_amount');
        $count = $completedOrders->count();

        return [
            'count' => $count,
            'total_amount' => $totalAmount,
            'average_order_value' => $count > 0 ? $totalAmount / $count : 0,
        ];
    }

    /**
     * Get failed orders data
     */
    protected function getFailedOrders(Carbon $weekStart, Carbon $weekEnd): array
    {
        $failedOrders = Order::where('status', Order::STATUS_FAILED)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $totalOrders = Order::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $failureRate = $totalOrders > 0 ? ($failedOrders / $totalOrders) * 100 : 0;

        return [
            'count' => $failedOrders,
            'failure_rate' => round($failureRate, 2),
        ];
    }

    /**
     * Get total commissions (fee amounts)
     */
    protected function getTotalCommissions(Carbon $weekStart, Carbon $weekEnd): array
    {
        $commissions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('SUM(fee_amount) as total_fees, COUNT(*) as transaction_count')
            ->first();

        return [
            'total_fee_amount' => $commissions->total_fees ?? 0,
            'transaction_count' => $commissions->transaction_count ?? 0,
        ];
    }

    /**
     * Get pending invoices
     */
    protected function getPendingInvoices(Carbon $weekStart, Carbon $weekEnd): array
    {
        $pendingInvoices = Invoice::where('status', 'pending')
            ->get();

        $totalPendingAmount = $pendingInvoices->sum('total_fee_amount');
        $overdueInvoices = $pendingInvoices->where('due_date', '<', Carbon::now())->count();

        return [
            'count' => $pendingInvoices->count(),
            'total_amount' => $totalPendingAmount,
            'overdue_count' => $overdueInvoices,
        ];
    }

    /**
     * Get published templates
     */
    protected function getPublishedTemplates(Carbon $weekStart, Carbon $weekEnd): array
    {
        $publishedTemplates = Template::where('status', 'published')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $totalPublishedTemplates = Template::where('status', 'published')->count();

        return [
            'new_this_week' => $publishedTemplates,
            'total' => $totalPublishedTemplates,
        ];
    }

    /**
     * Get published courses
     */
    protected function getPublishedCourses(Carbon $weekStart, Carbon $weekEnd): array
    {
        $publishedCourses = Course::where('status', 'published')
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->count();

        $totalPublishedCourses = Course::where('status', 'published')->count();

        return [
            'new_this_week' => $publishedCourses,
            'total' => $totalPublishedCourses,
        ];
    }

    /**
     * Get new enrollments
     */
    protected function getNewEnrollments(Carbon $weekStart, Carbon $weekEnd): array
    {
        $newEnrollments = Enrollment::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $totalEnrollments = Enrollment::count();

        return [
            'new_this_week' => $newEnrollments,
            'total' => $totalEnrollments,
        ];
    }

    /**
     * Get active users (users who performed any action this week)
     */
    protected function getActiveUsers(Carbon $weekStart, Carbon $weekEnd): array
    {
        $activeUsers = User::where('updated_at', '>=', $weekStart)
            ->where('updated_at', '<=', $weekEnd)
            ->count();

        return [
            'active_this_week' => $activeUsers,
        ];
    }

    /**
     * Get revenue breakdown
     */
    protected function getRevenueBreakdown(Carbon $weekStart, Carbon $weekEnd): array
    {
        $transactions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->selectRaw('
                SUM(amount) as gross_revenue,
                SUM(fee_amount) as commission_revenue,
                SUM(tax_amount) as tax_revenue,
                SUM(total_amount) as total_revenue
            ')
            ->first();

        return [
            'gross_revenue' => $transactions->gross_revenue ?? 0,
            'commission_revenue' => $transactions->commission_revenue ?? 0,
            'tax_revenue' => $transactions->tax_revenue ?? 0,
            'total_revenue' => $transactions->total_revenue ?? 0,
        ];
    }

    /**
     * Get top performing environments
     */
    protected function getTopEnvironments(Carbon $weekStart, Carbon $weekEnd): array
    {
        $topEnvironments = Environment::withCount(['orders' => function($query) use ($weekStart, $weekEnd) {
                $query->where('status', Order::STATUS_COMPLETED)
                      ->whereBetween('created_at', [$weekStart, $weekEnd]);
            }])
            ->having('orders_count', '>', 0)
            ->orderBy('orders_count', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'primary_domain'])
            ->map(function($env) {
                return [
                    'name' => $env->name,
                    'domain' => $env->primary_domain,
                    'orders_count' => $env->orders_count,
                ];
            });

        return $topEnvironments->toArray();
    }

    /**
     * Generate HTML report content
     */
    protected function generateReportContent(array $metrics, Carbon $weekStart, Carbon $weekEnd): string
    {
        $weekRange = $weekStart->format('M j, Y') . ' - ' . $weekEnd->format('M j, Y');
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .metric-card { background-color: #fff; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
                .metric-title { font-size: 18px; font-weight: bold; color: #333; margin-bottom: 10px; }
                .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
                .metric-subtitle { font-size: 14px; color: #666; margin-top: 5px; }
                .section { margin: 20px 0; }
                .table { border-collapse: collapse; width: 100%; margin-top: 10px; }
                .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .table th { background-color: #f8f9fa; }
                .positive { color: #28a745; }
                .negative { color: #dc3545; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>Weekly Analytics Report</h1>
                <p><strong>Period:</strong> {$weekRange}</p>
                <p><strong>Generated:</strong> " . Carbon::now()->format('M j, Y H:i:s') . "</p>
            </div>

            <div class='section'>
                <h2>Key Metrics Summary</h2>
                
                <div class='metric-card'>
                    <div class='metric-title'>New Registrations</div>
                    <div class='metric-value'>{$metrics['new_registrations']['total']}</div>
                    <div class='metric-subtitle'>New users this week</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Learning Environments</div>
                    <div class='metric-value'>{$metrics['learning_environments']['total']}</div>
                    <div class='metric-subtitle'>{$metrics['learning_environments']['active']} active, {$metrics['learning_environments']['new_this_week']} new this week</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Sales Performance</div>
                    <div class='metric-value'>{$metrics['completed_orders']['count']}</div>
                    <div class='metric-subtitle'>Completed orders (\${$metrics['completed_orders']['total_amount']} revenue)</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Failed Orders</div>
                    <div class='metric-value'>{$metrics['failed_orders']['count']}</div>
                    <div class='metric-subtitle'>{$metrics['failed_orders']['failure_rate']}% failure rate</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Commission Revenue</div>
                    <div class='metric-value'>\${$metrics['total_commissions']['total_fee_amount']}</div>
                    <div class='metric-subtitle'>From {$metrics['total_commissions']['transaction_count']} transactions</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Pending Invoices</div>
                    <div class='metric-value'>{$metrics['pending_invoices']['count']}</div>
                    <div class='metric-subtitle'>\${$metrics['pending_invoices']['total_amount']} total ({$metrics['pending_invoices']['overdue_count']} overdue)</div>
                </div>

                <div class='metric-card'>
                    <div class='metric-title'>Published Content</div>
                    <div class='metric-value'>{$metrics['published_templates']['new_this_week']}</div>
                    <div class='metric-subtitle'>New course templates, {$metrics['published_courses']['new_this_week']} new courses</div>
                </div>
            </div>

            <div class='section'>
                <h2>Revenue Breakdown</h2>
                <table class='table'>
                    <tr>
                        <th>Revenue Type</th>
                        <th>Amount</th>
                    </tr>
                    <tr>
                        <td>Gross Revenue</td>
                        <td>\${$metrics['revenue_breakdown']['gross_revenue']}</td>
                    </tr>
                    <tr>
                        <td>Commission Revenue</td>
                        <td>\${$metrics['revenue_breakdown']['commission_revenue']}</td>
                    </tr>
                    <tr>
                        <td>Tax Revenue</td>
                        <td>\${$metrics['revenue_breakdown']['tax_revenue']}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Revenue</strong></td>
                        <td><strong>\${$metrics['revenue_breakdown']['total_revenue']}</strong></td>
                    </tr>
                </table>
            </div>";

        if (!empty($metrics['top_environments'])) {
            $html .= "
            <div class='section'>
                <h2>Top Performing Environments</h2>
                <table class='table'>
                    <tr>
                        <th>Environment</th>
                        <th>Domain</th>
                        <th>Orders</th>
                    </tr>";
            
            foreach ($metrics['top_environments'] as $env) {
                $html .= "<tr>
                    <td>{$env['name']}</td>
                    <td>{$env['domain']}</td>
                    <td>{$env['orders_count']}</td>
                </tr>";
            }
            
            $html .= "</table></div>";
        }

        $html .= "
            <div class='section'>
                <h2>Additional Metrics</h2>
                <ul>
                    <li><strong>New Enrollments:</strong> {$metrics['new_enrollments']['new_this_week']}</li>
                    <li><strong>Active Users:</strong> {$metrics['active_users']['active_this_week']}</li>
                    <li><strong>Total Published Templates:</strong> {$metrics['published_templates']['total']}</li>
                    <li><strong>Total Published Courses:</strong> {$metrics['published_courses']['total']}</li>
                </ul>
            </div>

            <div class='section'>
                <p><em>This report is automatically generated by the CSL Certification API system.</em></p>
            </div>
        </body>
        </html>";

        return $html;
    }

    /**
     * Display report in console
     */
    protected function displayReport(array $metrics, Carbon $weekStart, Carbon $weekEnd)
    {
        $this->info('=== Weekly Analytics Report ===');
        $this->info("Period: {$weekStart->format('M j, Y')} - {$weekEnd->format('M j, Y')}");
        $this->info('');
        
        $this->info('📊 Key Metrics:');
        $this->info("  New Registrations: {$metrics['new_registrations']['total']}");
        $this->info("  Learning Environments: {$metrics['learning_environments']['total']} ({$metrics['learning_environments']['active']} active)");
        $this->info("  Completed Orders: {$metrics['completed_orders']['count']} (\${$metrics['completed_orders']['total_amount']})");
        $this->info("  Failed Orders: {$metrics['failed_orders']['count']} ({$metrics['failed_orders']['failure_rate']}% failure rate)");
        $this->info("  Commission Revenue: \${$metrics['total_commissions']['total_fee_amount']}");
        $this->info("  Pending Invoices: {$metrics['pending_invoices']['count']} (\${$metrics['pending_invoices']['total_amount']})");
        $this->info("  Published Templates: {$metrics['published_templates']['new_this_week']} new, {$metrics['published_templates']['total']} total");
        $this->info("  Published Courses: {$metrics['published_courses']['new_this_week']} new, {$metrics['published_courses']['total']} total");
        $this->info('');
    }

    /**
     * Send email report using Laravel Mail facade (with PHPMailer transport)
     */
    protected function sendEmailReport(string $content, Carbon $weekStart, Carbon $weekEnd)
    {
        $subject = 'CSL e-Learning - Weekly Analytics Report ' . $weekStart->format('M j') . ' to ' . $weekEnd->format('M j, Y');
        
        try {
            Mail::send([], [], function ($message) use ($subject, $content) {
                $message->to($this->recipients)
                        ->subject($subject)
                        ->from('data.analyst@cfpcsl.com', 'CSL e-Learning Analytics')
                        ->html($content);
            });
            
            $this->info('Weekly analytics report sent to: ' . implode(', ', $this->recipients));
            
        } catch (\Exception $e) {
            Log::error('Failed to send weekly analytics report email: ' . $e->getMessage());
            throw new \RuntimeException("Email could not be sent. Error: {$e->getMessage()}");
        }
    }
}