<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Modules\Core\Identity\PhoneNormalizer;

class BackfillCanonicalPhonesCommand extends Command
{
    /**
     * El nombre y firma del comando Artisan.
     *
     * @var string
     */
    protected $signature = 'aiva:backfill-canonical-phones';

    /**
     * La descripción del comando Artisan.
     *
     * @var string
     */
    protected $description = 'Ejecuta el backfill retroactivo seguro por lotes (chunkById) del teléfono canónico en las tablas orders y customers.';

    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        $this->info('Iniciando Backfill Retroactivo Canónico de Teléfonos...');

        // 1. Process orders in chunks of 100
        $processedOrders = 0;
        DB::table('orders')->whereNull('customer_phone_canonical')->orWhere('customer_phone_canonical', '')->orderBy('id')->chunkById(100, function ($orders) use (&$processedOrders) {
            foreach ($orders as $o) {
                if (!empty($o->customer_phone)) {
                    $canonical = PhoneNormalizer::toCanonical($o->customer_phone);
                    DB::table('orders')->where('id', $o->id)->update(['customer_phone_canonical' => $canonical]);
                    $processedOrders++;
                }
            }
        });

        // 2. Process customers in chunks of 100
        $processedCustomers = 0;
        DB::table('customers')->whereNull('customer_phone_canonical')->orWhere('customer_phone_canonical', '')->orderBy('id')->chunkById(100, function ($customers) use (&$processedCustomers) {
            foreach ($customers as $c) {
                if (!empty($c->phone)) {
                    $canonical = PhoneNormalizer::toCanonical($c->phone);
                    DB::table('customers')->where('id', $c->id)->update(['customer_phone_canonical' => $canonical]);
                    $processedCustomers++;
                }
            }
        });

        $this->info("Backfill finalizado con éxito. Ordenes procesadas: {$processedOrders}, Clientes procesados: {$processedCustomers}.");
        return 0;
    }
}
