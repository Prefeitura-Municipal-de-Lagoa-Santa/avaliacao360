<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    use HasFactory;

    // Defina os campos que podem ser preenchidos em massa
    protected $fillable = [
        'question_id',
        'evaluation_id',
        'response_content',
        'subject_person_id',
    ];

    /**
     * Relação: Uma resposta pertence a uma pergunta.
     */
    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Relação: Uma resposta pertence a uma avaliação.
     */
    public function evaluation()
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Relação: Usuário avaliado (sujeito).
     * Ajuste se o relacionamento for com outra tabela.
     */
    public function subjectPerson()
    {
        return $this->belongsTo(User::class, 'subject_person_id');
        // Se for para outra model, ex: Person::class
        // return $this->belongsTo(Person::class, 'subject_person_id');
    }
}
