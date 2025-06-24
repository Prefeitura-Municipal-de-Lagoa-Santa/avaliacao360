<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'code',
        'description',
        'parent_id',
    ];

    /**
     * Get the parent unit of this organizational unit.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'parent_id');
    }

    /**
     * Define um relacionamento recursivo para carregar todos os pais.
     */
    public function allParents(): BelongsTo
    {
        return $this->parent()->with('allParents');
    }

    /**
     * Este método implementa a sua lógica de "subir" na hierarquia.
     */
    public function getSecretariaAttribute(): ?OrganizationalUnit
    {
        $current = $this;

        // Loop para "subir" na árvore de unidades organizacionais
        while ($current) {
            
            if ($current->type === 'Secretaria') {
                return $current;
            }

            // Se não, vá para o pai e continue o loop
            $current = $current->parent;
        }

        // Retorna null se nenhuma secretaria for encontrada na hierarquia
        return null;
    }
    /**
     * Get the child units of this organizational unit.
     */
    public function children(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class, 'parent_id');
    }

    /**
     * Get the people associated with this organizational unit.
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class, 'organizational_unit_id');
    }

    /**
     * Get the root organizational units (those without a parent).
     */
    public static function getRootUnits()
    {
        return self::whereNull('parent_id')->get();
    }
}