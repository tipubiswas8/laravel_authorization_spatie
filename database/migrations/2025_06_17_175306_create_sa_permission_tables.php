<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teams = config('permission.teams');
        $pivotRole = 'sa_role_id';
        $pivotPermission = 'sa_permission_id';

        throw_if($teams && empty(config('permission.column_names.team_foreign_key')), new Exception('Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.'));

        Schema::create('sa_permissions', static function (Blueprint $table) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // permission id
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('sa_roles', static function (Blueprint $table) use ($teams) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // role id
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger('team_foreign_key')->nullable();
                $table->index('team_foreign_key', 'sa_roles_team_foreign_key_index');
            }
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique(['team_foreign_key', 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });
        Schema::create('sa_model_has_sa_permissions', static function (Blueprint $table) use ($pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger('model_id'); // ✅ Must be 'model_id'
            $table->index(['model_id', 'model_type'], 'sa_model_has_sa_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on('sa_permissions')
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger('team_foreign_key');
                $table->index('team_foreign_key', 'sa_model_has_sa_permissions_team_foreign_key_index');

                $table->primary(
                    ['team_foreign_key', $pivotPermission, 'model_id', 'model_type'],
                    'sa_model_has_sa_permissions_permission_model_type_primary'
                );
            } else {
                $table->primary(
                    [$pivotPermission, 'model_id', 'model_type'],
                    'sa_model_has_sa_permissions_permission_model_type_primary'
                );
            }
        });

        Schema::create('sa_model_has_sa_roles', static function (Blueprint $table) use ($pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger('model_id'); // ✅ Correct column name
            $table->index(['model_id', 'model_type'], 'sa_model_has_sa_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on('sa_roles')
                ->onDelete('cascade');

            if ($teams) {
                $table->unsignedBigInteger('team_foreign_key');
                $table->index('team_foreign_key', 'sa_model_has_sa_roles_team_foreign_key_index');

                $table->primary(
                    ['team_foreign_key', $pivotRole, 'model_id', 'model_type'],
                    'sa_model_has_sa_roles_role_model_type_primary'
                );
            } else {
                $table->primary(
                    [$pivotRole, 'model_id', 'model_type'],
                    'sa_model_has_sa_roles_role_model_type_primary'
                );
            }
        });


        Schema::create('sa_role_has_sa_permissions', static function (Blueprint $table) use ($pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on('sa_permissions')
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on('sa_roles')
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'sa_role_has_sa_permissions_sa_permission_id_sa_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sa_role_has_sa_permissions');
        Schema::dropIfExists('sa_model_has_sa_roles');
        Schema::dropIfExists('sa_model_has_sa_permissions');
        Schema::dropIfExists('sa_roles');
        Schema::dropIfExists('sa_permissions');
    }
};
