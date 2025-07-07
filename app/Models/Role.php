<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;  // Utiliza o trait HasUuids para permitir a geração de UUIDs para o modelo
use Illuminate\Database\Eloquent\Factories\HasFactory; // Permite o uso de fábricas de modelo
use Illuminate\Database\Eloquent\Model;  // Classe base para todos os modelos Eloquent
use Illuminate\Database\Eloquent\Relations\BelongsToMany; // Importa a relação 'BelongsToMany'

class Role extends Model
{
    // Usa os traits HasFactory e HasUuids
    use HasFactory, HasUuids;

    // Campos que podem ser preenchidos diretamente no modelo
    protected $fillable = [
        'name',        // Nome do papel/role
        'description', // Descrição do papel/role
        'level',       // Nível ou hierarquia do papel/role
    ];

    // Relacionamento: Um papel pode ser atribuído a vários usuários
    public function users(): BelongsToMany
    {
        // Relacionamento muitos-para-muitos com o modelo User
        // Um usuário pode ter muitos papéis, e um papel pode pertencer a muitos usuários
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id');
    }
    
}
