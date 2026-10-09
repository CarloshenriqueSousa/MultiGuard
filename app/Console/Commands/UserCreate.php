<?php
/**
 * Cria ou atualiza um usuario interno do console (tabela users) pedindo a senha no terminal, sem eco. Se o email
 * ja existe, troca nome e senha. A senha e gravada com Hash::make direto na tabela para nao depender dos casts
 * do modelo User da versao instalada do Laravel.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserCreate extends Command
{
    protected $signature = 'mg:user-create {email} {--name=Operador}';

    protected $description = 'Cria ou atualiza um usuario interno do console';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = (string) $this->secret('Senha (mínimo 8 caracteres)');

        if (strlen($password) < 8) {
            $this->error('Senha curta demais.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Repita a senha')) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }

        $values = [
            'name' => $this->option('name'),
            'password' => Hash::make($password),
            'updated_at' => now(),
        ];

        if (DB::table('users')->where('email', $email)->exists()) {
            DB::table('users')->where('email', $email)->update($values);
        } else {
            DB::table('users')->insert($values + ['email' => $email, 'created_at' => now()]);
        }

        $this->info("Usuário {$email} pronto.");

        return self::SUCCESS;
    }
}
