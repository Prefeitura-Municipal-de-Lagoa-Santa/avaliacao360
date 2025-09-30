<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Parcial Da Avaliação Periódica De Desempenho</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
        }
        
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        
        .info-row {
            display: table-row;
        }
        
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 5px 10px 5px 0;
            width: 30%;
            vertical-align: top;
        }
        
        .info-value {
            display: table-cell;
            padding: 5px 0;
            vertical-align: top;
        }
        
        .questions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .questions-table th,
        .questions-table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
        }
        
        .questions-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }
        
        .score-cell {
            text-align: center;
            font-weight: bold;
        }
        
        .score-excellent { color: #059669; }
        .score-good { color: #0891b2; }
        .score-average { color: #d97706; }
        .score-poor { color: #dc2626; }
        .score-na { color: #6b7280; }
        
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .score-highlight {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            text-align: center;
            margin: 10px 0;
        }
        
        .evidencias-box {
            background-color: #fefefe;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 15px;
            margin-top: 10px;
            min-height: 80px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
        
        .signature-section {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            background-color: #f9fafb;
        }
        
        .signature-image {
            max-width: 300px;
            max-height: 100px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background-color: white;
            padding: 5px;
        }
        
        .signature-label {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
            font-weight: bold;
        }
        
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Relatório Parcial Da Avaliação Periódica De Desempenho</div>
        <div class="subtitle">Sistema de Avaliação 360°</div>
    </div>

    <div class="section">
        <div class="section-title">Informações da Avaliação</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Formulário:</div>
                <div class="info-value">{{ $form->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Ano:</div>
                <div class="info-value">{{ $form->year ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo de Avaliação:</div>
                <div class="info-value">{{ ucfirst($evaluation->type ?? 'N/A') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data de Conclusão:</div>
                <div class="info-value">{{ $completedAt ? $completedAt->format('d/m/Y H:i') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Dados do Avaliado</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value">{{ $evaluatedPerson->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Matrícula:</div>
                <div class="info-value">{{ $evaluatedPerson->registration_number ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Função:</div>
                <div class="info-value">{{ $evaluatedPerson->jobFunction?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Sala:</div>
                <div class="info-value">{{ $evaluatedPerson->descricao_sala ?? $evaluatedPerson->sala ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Secretaria:</div>
                <div class="info-value">{{ $evaluatedPerson->organizationalUnit?->secretaria?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data de Admissão:</div>
                <div class="info-value">{{ $evaluatedPerson->admission_date ? $evaluatedPerson->admission_date->format('d/m/Y') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Dados do Avaliador</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value">{{ $evaluatorPerson->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Matrícula:</div>
                <div class="info-value">{{ $evaluatorPerson->registration_number ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Cargo:</div>
                <div class="info-value">{{ $evaluatorPerson->current_position ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Função:</div>
                <div class="info-value">{{ $evaluatorPerson->jobFunction?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Sala:</div>
                <div class="info-value">{{ $evaluatorPerson->descricao_sala ?? $evaluatorPerson->sala ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Secretaria:</div>
                <div class="info-value">{{ $evaluatorPerson->organizationalUnit?->secretaria?->name ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data de Admissão:</div>
                <div class="info-value">{{ $evaluatorPerson->admission_date ? $evaluatorPerson->admission_date->format('d/m/Y') : 'N/A' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Resumo da Avaliação</div>
        <div class="summary-box">
            <div class="score-highlight">Nota Final: {{ number_format($averageScore, 2, ',', '.') }}</div>
            <div style="text-align: center; margin-top: 10px;">
                @if($averageScore >= 4.5)
                    <span style="color: #059669; font-weight: bold;">Excelente</span>
                @elseif($averageScore >= 3.5)
                    <span style="color: #0891b2; font-weight: bold;">Bom</span>
                @elseif($averageScore >= 2.5)
                    <span style="color: #d97706; font-weight: bold;">Regular</span>
                @else
                    <span style="color: #dc2626; font-weight: bold;">Insatisfatório</span>
                @endif
            </div>
        </div>
    </div>

    @if(isset($groupedQuestionAnswers) && count($groupedQuestionAnswers) > 0)
    <div class="section page-break">
        <div class="section-title">Detalhamento das Questões por Competência</div>

        @foreach($groupedQuestionAnswers as $group)
            <div class="section" style="margin-top: 10px;">
                <div class="section-title" style="font-size: 14px; color: #374151; border-bottom-color: #f3f4f6;">{{ $group['group'] }}</div>
                <table class="questions-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Questão</th>
                            <th style="width: 15%;">Nota</th>
                            <th style="width: 15%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['questions'] as $qa)
                        <tr>
                            <td>{{ $qa['question'] }}</td>
                            <td class="score-cell
                                @if($qa['score'] === null) score-na
                                @elseif($qa['score'] >= 4) score-excellent
                                @elseif($qa['score'] >= 3) score-good
                                @elseif($qa['score'] >= 2) score-average
                                @else score-poor
                                @endif
                            ">
                                {{ $qa['score'] ?? 'N/A' }}
                            </td>
                            <td style="text-align: center;">
                                @if($qa['score'] === null)
                                    <span style="color: #6b7280;">N/R</span>
                                @elseif($qa['score'] >= 4)
                                    <span style="color: #059669;">✓</span>
                                @elseif($qa['score'] >= 3)
                                    <span style="color: #0891b2;">~</span>
                                @else
                                    <span style="color: #dc2626;">✗</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
    @endif

    @if($evidencias)
    <div class="section">
        <div class="section-title">Evidências</div>
        <div class="evidencias-box">
            {{ $evidencias }}
        </div>
    </div>
    @endif

    @if($assinatura_base64)
    <div class="section">
        <div class="section-title">Assinatura do Avaliador</div>
        <div class="signature-section">
            <img src="{{ $assinatura_base64 }}" alt="Assinatura" class="signature-image">
            <div class="signature-label">
                {{ $evaluatorPerson->name ?? 'Avaliador' }}<br>
                <span style="font-weight: normal; font-size: 10px;">Assinatura Digital</span>
            </div>
        </div>
    </div>
    @endif

    <div class="footer">
        <p>Relatório gerado automaticamente pelo Sistema de Avaliação 360° em {{ date('d/m/Y H:i') }}</p>
        <p>Documento confidencial - Para uso interno apenas</p>
    </div>
</body>
</html>
