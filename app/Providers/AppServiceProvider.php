<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     * (Phân trang dùng view Tailwind mặc định của Laravel, không dùng Bootstrap.)
     */
    public function boot(): void
    {
        // Ngoài production: truy cập quan hệ chưa eager load trên một tập model sẽ ném
        // LazyLoadingViolationException => phát hiện N+1 ngay khi phát triển.
        // Production không bật để một lỗi sót không làm sập trang.
        Model::preventLazyLoading(! $this->app->environment('production'));
    }
}
