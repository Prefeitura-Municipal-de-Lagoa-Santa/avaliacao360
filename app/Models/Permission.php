<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;  // Utiliza o trait HasUuids para permitir a geração de UUIDs para o modelo
use Illuminate\Database\Eloquent\Factories\HasFactory; // Permite o uso de fábricas de modelo
use Illuminate\Database\Eloquent\Model;  // Classe base para todos os modelos Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Importa a relação 'BelongsToMany'

class Permission extends Model
{
    // Usa os traits HasFactory e HasUuids
    use HasFactory, HasUuids;

    // Campos que podem ser preenchidos diretamente no modelo
    protected $fillable = [
        'name',        // Nome da permissão
        'description', // Descrição da permissão
    ];

    // Relacionamento: Uma permissão pode ser atribuída a vários usuários
    public function users(): BelongsToMany
    {
        // Relacionamento muitos-para-muitos com o modelo User
        // Um usuário pode ter muitas permissões, e uma permissão pode pertencer a vários usuários
        return $this->belongsToMany(User::class);
    }

    // Relacionamento: Uma permissão pode ser atribuída a vários papéis (roles)
    public function roles()
    {
        // Relacionamento muitos-para-muitos com o modelo Role
        // Um papel pode ter muitas permissões, e uma permissão pode pertencer a vários papéis
        return $this->belongsToMany(Role::class, 'role_permission', 'permission_id', 'role_id');
    }
}
