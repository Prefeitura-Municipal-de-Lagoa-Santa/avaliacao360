<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Message;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $defaultPassword
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $appUrl = config('app.url');
            $appName = config('app.name', 'Sistema de Avaliação 360°');
            
            Mail::send([], [], function (Message $message) use ($appUrl, $appName) {
                $message->to($this->user->email, $this->user->name)
                        ->subject("Bem-vindo ao {$appName}")
                        ->html($this->getEmailContent($appUrl, $appName));
            });
        } catch (\Exception $e) {
            // Log do erro sem falhar o job
            Log::error('Erro ao enviar email de boas-vindas', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getEmailContent(string $appUrl, string $appName): string
    {
        $formattedCpf = $this->formatCpfForDisplay($this->user->cpf);
        
        return "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8f9fa;'>
            <div style='background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);'>
                <!-- Header -->
                <div style='text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #007bff;'>
                    <h1 style='color: #007bff; margin: 0; font-size: 28px; font-weight: 700;'>{$appName}</h1>
                    <p style='color: #6c757d; margin: 5px 0 0 0; font-size: 16px;'>Prefeitura Municipal de Lagoa Santa</p>
                </div>
                
                <!-- Welcome Message -->
                <div style='margin-bottom: 30px;'>
                    <h2 style='color: #343a40; margin-bottom: 20px; font-size: 24px;'>Bem-vindo(a), {$this->user->name}!</h2>
                    
                    <p style='color: #495057; font-size: 16px; line-height: 1.6; margin-bottom: 20px;'>
                        Sua conta foi criada com sucesso no sistema de avaliação 360° da Prefeitura Municipal de Lagoa Santa.
                    </p>
                    
                    <p style='color: #495057; font-size: 16px; line-height: 1.6; margin-bottom: 30px;'>
                        Use as credenciais abaixo para fazer seu primeiro acesso ao sistema. <strong>O login é feito com seu CPF</strong> (sem pontos ou traços):
                    </p>
                </div>
                
                <!-- Credentials Box -->
                <div style='background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; border-left: 5px solid #007bff; margin: 30px 0;'>
                    <h3 style='color: #007bff; margin: 0 0 20px 0; font-size: 20px; display: flex; align-items: center;'>
                        🔑 Dados de Acesso
                    </h3>
                    
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #495057; display: inline-block; width: 120px;'>Sistema:</strong>
                        <a href='{$appUrl}' style='color: #007bff; text-decoration: none; font-weight: 500;'>{$appUrl}</a>
                    </div>
                    
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #495057; display: inline-block; width: 120px;'>CPF/Login:</strong>
                        <span style='color: #343a40; font-family: monospace; background-color: #fff; padding: 8px 12px; border-radius: 4px; border: 2px solid #28a745; font-weight: bold; font-size: 16px;'>{$formattedCpf}</span>
                        <br><small style='color: #6c757d; font-size: 12px; margin-left: 120px; display: inline-block; margin-top: 5px;'>* Digite apenas os números: {$this->user->cpf}</small>
                    </div>
                    
                    <div style='margin-bottom: 0;'>
                        <strong style='color: #495057; display: inline-block; width: 120px;'>Senha:</strong>
                        <span style='color: #343a40; font-family: monospace; background-color: #fff; padding: 8px 12px; border-radius: 4px; border: 2px solid #007bff; font-weight: bold; font-size: 16px;'>{$this->defaultPassword}</span>
                    </div>
                </div>
                
                <!-- Security Warning -->
                <div style='background-color: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 30px 0;'>
                    <div style='display: flex; align-items: flex-start;'>
                        <span style='font-size: 20px; margin-right: 10px;'>⚠️</span>
                        <div>
                            <p style='color: #856404; margin: 0 0 8px 0; font-weight: 600; font-size: 16px;'>Importante - Primeira Senha</p>
                            <p style='color: #856404; margin: 0; font-size: 14px; line-height: 1.5;'>
                                Por questões de segurança, você será solicitado a alterar sua senha no primeiro acesso ao sistema.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Login Instructions -->
                <div style='background-color: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; margin: 30px 0;'>
                    <div style='display: flex; align-items: flex-start;'>
                        <span style='font-size: 20px; margin-right: 10px;'>ℹ️</span>
                        <div>
                            <p style='color: #0c5460; margin: 0 0 8px 0; font-weight: 600; font-size: 16px;'>Como fazer login</p>
                            <p style='color: #0c5460; margin: 0; font-size: 14px; line-height: 1.5;'>
                                • Digite seu <strong>CPF</strong> no campo de login (apenas números)<br>
                                • Digite a <strong>senha temporária</strong> fornecida acima<br>
                                • Clique em \"Entrar\" e siga as instruções para alterar sua senha
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Action Button -->
                <div style='text-align: center; margin: 40px 0;'>
                    <a href='{$appUrl}' style='background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; padding: 15px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 6px rgba(0, 123, 255, 0.3); transition: all 0.3s ease;'>
                        🚀 Acessar Sistema
                    </a>
                </div>
                
                <!-- Support Info -->
                <div style='background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;'>
                    <p style='color: #6c757d; margin: 0; font-size: 14px; line-height: 1.5; text-align: center;'>
                        💬 <strong>Precisa de ajuda?</strong><br>
                        Entre em contato com o suporte técnico da Prefeitura Municipal de Lagoa Santa
                    </p>
                </div>
                
                <!-- Footer -->
                <div style='border-top: 1px solid #dee2e6; margin-top: 40px; padding-top: 20px; text-align: center;'>
                    <p style='color: #adb5bd; font-size: 12px; margin: 0;'>
                        © " . date('Y') . " Prefeitura Municipal de Lagoa Santa<br>
                        Este é um e-mail automático, por favor não responda.
                    </p>
                </div>
            </div>
        </div>";
    }

    private function formatCpfForDisplay(string $cpf): string
    {
        // Formata CPF: 123.456.789-01
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
}
