<?php

namespace App\Providers;

use App\Services\Discovery\EventDiscoverySearchGateway;
use App\Services\Discovery\MeilisearchEventDiscoverySearchGateway;
use App\Services\Search\EventSearchGateway;
use App\Services\Search\MeilisearchEventSearchGateway;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Meilisearch\Client as MeilisearchClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MeilisearchClient::class, function (): MeilisearchClient {
            $timeout = (float) config('meilisearch.http_timeout_seconds', 5);
            $key = config('meilisearch.key');

            return new MeilisearchClient(
                (string) config('meilisearch.host', 'http://127.0.0.1:7700'),
                is_string($key) && $key !== '' ? $key : null,
                new HttpClient([
                    'connect_timeout' => $timeout,
                    'timeout' => $timeout,
                ]),
            );
        });

        $this->app->bind(EventSearchGateway::class, MeilisearchEventSearchGateway::class);
        $this->app->bind(EventDiscoverySearchGateway::class, MeilisearchEventDiscoverySearchGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBrandedMail();
    }

    /**
     * Send the email-verification message through the branded template
     * instead of the default framework theme.
     */
    protected function configureBrandedMail(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $name = $notifiable->name ?? 'there';

            return (new MailMessage)
                ->subject('Verify your EventRadar email')
                ->view(['mail.verify-email', 'mail.verify-email-text'], [
                    'name' => $name,
                    'url' => $url,
                    'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
