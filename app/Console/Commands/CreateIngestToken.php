<?php

namespace App\Console\Commands;

use App\Models\IngestToken;
use App\Models\Unit;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateIngestToken extends Command
{
    protected $signature = 'mg:token-create {unit : slug da unidade} {--name=agent}';
    protected $description = 'Gera um token de ingestão para uma unidade';

    public function handle(): int
    {
        $unit = Unit::where('slug', $this->argument('unit'))->first();

        if (! $unit) {
            $this->error('Unidade não encontrada para o slug: ' . $this->argument('unit'));
            return self::FAILURE;
        }

        $plain = 'mg_' . Str::random(40);

        IngestToken::create([
            'unit_id' => $unit->id,
            'name' => $this->option('name'),
            'token_hash' => hash('sha256', $plain),
        ]);

        $this->info('Token de ingestão gerado com sucesso!');
        $this->warn('Copie e guarde agora (ele não poderá ser visto novamente):');
        $this->line($plain);

        return self::SUCCESS;
    }
}
