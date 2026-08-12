<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string RETIRED_TOKEN_PREFIX = 'retired-kingdom-intelligence-invitation:';

    public function up(): void
    {
        Schema::table('kingdom_intelligence_shares', function (Blueprint $table): void {
            $table->char('invitation_token_hash', 64)->nullable()->change();
        });

        $terminalRows = DB::table('kingdom_intelligence_shares')
            ->whereNotNull('invitation_token_hash')
            ->whereIn('state', ['active', 'declined', 'revoked'])
            ->select(['id', 'invitation_token_hash'])
            ->orderBy('id')
            ->get();

        foreach ($terminalRows as $terminalRow) {
            /** @var array{id: mixed, invitation_token_hash: mixed} $row */
            $row = (array) $terminalRow;
            $id = (string) $row['id'];
            $hash = (string) $row['invitation_token_hash'];

            if (! hash_equals($this->retiredHash($id), $hash)) {
                continue;
            }

            DB::table('kingdom_intelligence_shares')
                ->where('id', $id)
                ->update(['invitation_token_hash' => null]);
        }
    }

    public function down(): void
    {
        $rows = DB::table('kingdom_intelligence_shares')
            ->whereNull('invitation_token_hash')
            ->select(['id'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $result) {
            /** @var array{id: mixed} $row */
            $row = (array) $result;
            $id = (string) $row['id'];
            DB::table('kingdom_intelligence_shares')
                ->where('id', $id)
                ->update(['invitation_token_hash' => $this->retiredHash($id)]);
        }

        Schema::table('kingdom_intelligence_shares', function (Blueprint $table): void {
            $table->char('invitation_token_hash', 64)->nullable(false)->change();
        });
    }

    private function retiredHash(string $shareId): string
    {
        return hash('sha256', self::RETIRED_TOKEN_PREFIX.$shareId);
    }
};
