<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_inventory', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('principio_activo')->nullable();
            $table->enum('categoria', [
                'colirios', 'unguentos', 'oral', 'inyectables', 'lagrimas',
                'antiglaucomatosos', 'antibioticos', 'antiinflamatorios', 'otros',
            ])->default('otros');
            $table->string('presentacion')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(5);
            $table->decimal('precio', 10, 2)->nullable();
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_inventory');
    }
};
