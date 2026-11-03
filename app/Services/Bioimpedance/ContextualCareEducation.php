<?php

namespace App\Services\Bioimpedance;

class ContextualCareEducation
{
    public function claims(array $context): array
    {
        $findings = collect($context['findings'] ?? []);
        $fat = $findings->first(fn (array $finding) => in_array($finding['metric'], ['fat', 'body_fat_percentage'], true) && $finding['priority'] === 'review');
        $muscle = $findings->first(fn (array $finding) => in_array($finding['metric'], ['muscle', 'skeletal_muscle_percentage'], true) && $finding['priority'] === 'review');
        $water = $findings->firstWhere('metric', 'body_water_percentage');
        $weight = $findings->firstWhere('metric', 'weight_kg');
        $baseline = ! data_get($context, 'history_comparison.has_previous');
        $older = ($context['age_at_assessment'] ?? 0) >= 65;

        $nutrition = $fat
            ? 'A classificação da gordura registrada nesta avaliação não é uma indicação de dieta. Para a revisão com o profissional, observe a composição habitual das refeições e a presença de ultraprocessados; o Guia Alimentar oferece opções com alimentos in natura e minimamente processados, sem definir quantidades individuais.'
            : ($water
                ? 'A água corporal registrada é uma estimativa e não informa quanto você precisa beber ou comer. Na próxima consulta, descreva sua alimentação habitual; o Guia Alimentar pode apoiar a escolha de refeições variadas com alimentos in natura, sem estabelecer uma dieta a partir da balança.'
                : 'Não há informação sobre sua alimentação nesta avaliação. Antes de propor mudanças, registre com o profissional como são suas refeições; use o Guia Alimentar como referência educativa, não como uma dieta individualizada.');
        $movement = $muscle
            ? 'A classificação muscular registrada não mede força, mobilidade ou tolerância ao esforço. Antes de escolher atividades, peça ao profissional que avalie essas capacidades; possibilidades de movimento e fortalecimento devem respeitar suas condições e eventual necessidade de liberação.'
            : ($older
                ? 'A idade registrada torna relevante conversar sobre equilíbrio, mobilidade e autonomia, sem presumir limitações. As opções de atividade devem ser definidas após conhecer sua rotina, capacidade e condições de saúde.'
                : 'A balança não informa seu nível de atividade ou condicionamento. Converse sobre sua rotina e atividades de que gosta antes de escolher possibilidades de movimento; qualquer progressão depende da capacidade individual e da avaliação profissional.');
        $followUp = $baseline
            ? 'Use esta avaliação como registro inicial. Na revisão, relate objetivos e condições da medição e combine com o profissional como acompanhar os resultados, sem adotar o peso-alvo do equipamento como meta pessoal.'
            : (data_get($context, 'history_comparison.automatic_interpretation_allowed')
                ? 'Leve ao profissional os registros comparáveis e as condições de cada medição. Discuta as diferenças antes de mudar alimentação ou atividade; variações das estimativas não demonstram, sozinhas, melhora ou piora clínica.'
                : 'Os registros anteriores não permitem uma comparação automática segura. Confira com o profissional o equipamento e o perfil de medição antes de atribuir significado às diferenças ou alterar seus cuidados.');

        return [
            $this->claim('nutrition-contextual', 'nutrition_education', $nutrition, [($fat ?? $water ?? $weight)['id'] ?? 'observation:approved', 'brazil-food-guide-natural-foods', 'brazil-food-guide-ultraprocessed']),
            $this->claim('movement-contextual', 'movement_education', $movement, [$muscle['id'] ?? 'observation:approved', $older ? 'who-older-adult-functional-activity' : 'who-adult-physical-activity', 'vita-scan-care-guidance-safety']),
            $this->claim('professional-follow-up', 'professional_follow_up', $followUp, [$baseline ? 'assessment:current' : 'assessment:history-profile', 'observation:approved', 'vita-scan-care-guidance-safety']),
        ];
    }

    private function claim(string $id, string $category, string $text, array $sources): array
    {
        return [
            'id' => $id, 'category' => $category, 'text' => $text,
            'confidence' => 'moderate', 'requires_professional_review' => true,
            'source_ids' => array_values(array_unique($sources)),
        ];
    }
}
