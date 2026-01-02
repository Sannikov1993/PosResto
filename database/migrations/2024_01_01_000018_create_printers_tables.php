<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Принтеры
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->string('name', 100);                    // Название (Касса, Кухня, Бар)
            $table->enum('type', ['receipt', 'kitchen', 'bar', 'label'])->default('receipt');
            $table->enum('connection', ['network', 'usb', 'bluetooth', 'file'])->default('network');
            $table->string('ip_address', 45)->nullable();   // для сетевых
            $table->integer('port')->default(9100);         // порт (обычно 9100)
            $table->string('device_path', 100)->nullable(); // /dev/usb/lp0 или COM1
            $table->integer('paper_width')->default(80);    // ширина бумаги 58/80мм
            $table->integer('chars_per_line')->default(48); // символов в строке
            $table->string('encoding', 20)->default('cp866'); // кодировка
            $table->boolean('cut_paper')->default(true);    // отрезать бумагу
            $table->boolean('open_drawer')->default(false); // открывать денежный ящик
            $table->boolean('print_logo')->default(false);  // печатать логотип
            $table->boolean('print_qr')->default(true);     // печатать QR-код
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();           // доп. настройки
            $table->timestamps();
            
            $table->index(['restaurant_id', 'type']);
        });

        // Задания на печать (очередь)
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('restaurant_id')->default(1);
            $table->unsignedBigInteger('printer_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->enum('type', ['receipt', 'kitchen', 'precheck', 'report'])->default('receipt');
            $table->enum('status', ['pending', 'printing', 'completed', 'failed'])->default('pending');
            $table->longText('content');                    // ESC/POS данные (base64)
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
            
            $table->index(['restaurant_id', 'status']);
            $table->index(['printer_id', 'status']);
        });

        // Базовые принтеры
        DB::table('printers')->insert([
            [
                'restaurant_id' => 1,
                'name' => 'Касса',
                'type' => 'receipt',
                'connection' => 'network',
                'ip_address' => '192.168.1.100',
                'port' => 9100,
                'paper_width' => 80,
                'chars_per_line' => 48,
                'encoding' => 'cp866',
                'cut_paper' => true,
                'open_drawer' => true,
                'print_logo' => false,
                'print_qr' => true,
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Кухня',
                'type' => 'kitchen',
                'connection' => 'network',
                'ip_address' => '192.168.1.101',
                'port' => 9100,
                'paper_width' => 80,
                'chars_per_line' => 48,
                'encoding' => 'cp866',
                'cut_paper' => true,
                'open_drawer' => false,
                'print_logo' => false,
                'print_qr' => false,
                'is_active' => true,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'restaurant_id' => 1,
                'name' => 'Бар',
                'type' => 'bar',
                'connection' => 'network',
                'ip_address' => '192.168.1.102',
                'port' => 9100,
                'paper_width' => 58,
                'chars_per_line' => 32,
                'encoding' => 'cp866',
                'cut_paper' => true,
                'open_drawer' => false,
                'print_logo' => false,
                'print_qr' => false,
                'is_active' => false,
                'is_default' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('printers');
    }
};
