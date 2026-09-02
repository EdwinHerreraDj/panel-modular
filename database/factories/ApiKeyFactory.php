<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = ApiKey::TOKEN_PREFIX.Str::random(48);

        return [
            'name' => 'Web '.fake()->word(),
            'prefix' => mb_substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'allowed_origins' => null,
            'rate_limit' => null,
            'created_by' => null,
            'last_used_at' => null,
            'last_used_ip' => null,
            'requests_count' => 0,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()->subDay()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    /**
     * @param  list<string>  $origins
     */
    public function forOrigins(array $origins): static
    {
        return $this->state(fn (): array => ['allowed_origins' => $origins]);
    }
}
