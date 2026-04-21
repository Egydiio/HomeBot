<?php

namespace App\Services\Bot;

use Illuminate\Support\Facades\Redis;

class ConversationState
{
    // Estados possíveis da conversa
    const STATE_IDLE                       = 'idle';
    const STATE_WAITING_CLASSIFICATION     = 'waiting_classification';
    const STATE_WAITING_CONFIRMATION       = 'waiting_confirmation';
    const STATE_WAITING_MANUAL_VALUE       = 'waiting_manual_value';
    const STATE_WAITING_IMAGE_TYPE         = 'waiting_image_type';
    const STATE_WAITING_ITEM_CLASSIFICATION = 'waiting_item_classification';

    // Tempo de expiração do estado — 30 minutos
    const TTL = 1800;

    private function stateKey(string $phone): string
    {
        return "homebot:state:{$phone}";
    }

    private function dataKey(string $phone): string
    {
        return "homebot:data:{$phone}";
    }

    // Retorna o estado atual do usuário
    public function getState(string $phone): string
    {
        return Redis::get($this->stateKey($phone)) ?? self::STATE_IDLE;
    }

    // Define o estado atual
    public function setState(string $phone, string $state): void
    {
        Redis::setex($this->stateKey($phone), self::TTL, $state);
    }

    // Salva dados temporários da conversa (ex: itens aguardando classificação)
    public function setData(string $phone, array $data): void
    {
        Redis::setex($this->dataKey($phone), self::TTL, json_encode($data));
    }

    // Recupera os dados temporários
    public function getData(string $phone): ?array
    {
        $data = Redis::get($this->dataKey($phone));
        return $data ? json_decode($data, true) : null;
    }

    // Limpa o estado e os dados — volta pro idle
    public function clear(string $phone): void
    {
        Redis::del($this->stateKey($phone));
        Redis::del($this->dataKey($phone));
    }

    // Verifica se está em determinado estado
    public function is(string $phone, string $state): bool
    {
        return $this->getState($phone) === $state;
    }

    // Verifica se está idle (sem conversa em andamento)
    public function isIdle(string $phone): bool
    {
        return $this->is($phone, self::STATE_IDLE);
    }
}
