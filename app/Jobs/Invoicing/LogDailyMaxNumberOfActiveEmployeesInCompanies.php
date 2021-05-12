<?php

namespace App\Jobs\Invoicing;

use Illuminate\Bus\Queueable;
use App\Models\Company\Company;
use App\Models\Company\Employee;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\Company\CompanyUsageHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Company\CompanyUsageHistoryDetails;

class LogDailyMaxNumberOfActiveEmployeesInCompanies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Record the number of active employee for all the companies in the
     * instance.
     */
    public function handle(): void
    {
        Company::addSelect([
            'max_employees' => Employee::selectRaw('count(*)')
                ->whereColumn('company_id', 'companies.id')
                ->whereColumn('locked', 0),
        ])
        ->chunk(100, function ($companies) {
            foreach ($companies as $company) {
                CompanyUsageHistory::create([
                    'company_id' => $company->id,
                    'number_of_active_employees' => $company->max_employees,
                ]);

                Employee::where('company_id', $company->id)
                    ->where('locked', 0)
                    ->chunk(100, function ($employees) use ($company) {
                        foreach ($employees as $employee) {
                            CompanyUsageHistoryDetails::create([
                                'company_id' => $company->id,
                                'employee_name' => $employee->name,
                                'employee_email' => $employee->email,
                            ]);
                        }
                    });
            }
        });
    }
}
