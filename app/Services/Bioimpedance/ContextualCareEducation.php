<?php

namespace App\Services\Bioimpedance;

class ContextualCareEducation
{
    public function __construct(
        private readonly AssessmentFindingPrioritizer $prioritizer = new AssessmentFindingPrioritizer,
        private readonly AssessmentNarrative $narrative = new AssessmentNarrative,
    ) {}

    public function claims(array $context): array
    {
        $findings = collect($this->prioritizer->ordered($context['findings'] ?? []));
        $fat = $findings->first(fn ($finding) => $finding['metric'] === 'fat' && $finding['priority'] === 'review')
            ?? $findings->firstWhere('metric', 'body_fat_percentage');
        $bmi = $findings->firstWhere('metric', 'calculated_bmi');
        $muscle = $findings->where('kind', 'segmental')->where('metric', 'muscle');
        $muscleOverview = $this->narrative->classificationOverview($muscle->all(), 'muscle');
        $older = ($context['age_at_assessment'] ?? 0) >= 65;
        $approved = filled($context['professional_observation'] ?? null) ? ['observation:approved'] : [];
        $nutritionEvidence = array_filter([$fat['id'] ?? null, $bmi['id'] ?? null]);
        $movementEvidence = $muscle->pluck('id')->all();
        if (! $movementEvidence) {
            $generalMuscle = $findings->firstWhere('metric', 'skeletal_muscle_percentage');
            $movementEvidence = array_filter([$generalMuscle['id'] ?? null]);
        }

        $nutrition = $this->nutritionFocus($bmi, $fat);
        $nutrition .= ' A distribuição da gordura não determina quais alimentos consumir ou evitar. Como orientação geral, e não tratamento de gordura em uma região, arroz e feijão, verduras, legumes e frutas podem compor refeições variadas. Discuta com o profissional sua alimentação habitual, preferências e restrições antes de definir quantidades ou substituições; a balança não informa esses hábitos.';

        $movement = $muscleOverview
            ? $muscleOverview.' '.$this->movementFocus($muscle->all())
            : 'A balança não informa seu nível de atividade ou condicionamento; a estimativa muscular não substitui avaliação funcional.';
        $movement .= $older
            ? ' Para discutir com o profissional: caminhadas confortáveis, equilíbrio com apoio e fortalecimento adaptado, após avaliar segurança e autonomia.'
            : ' Possibilidades gerais a discutir: caminhadas confortáveis, pausas para se movimentar e fortalecimento orientado, como sentar e levantar quando adequado à sua capacidade.';
        $movement .= ' Registre com o profissional condicionamento atual, limitações, sintomas e objetivos antes de escolher atividades. IMC e estimativas musculares não bastam; escolha e progressão exigem avaliação individual e eventual liberação profissional, sem carga, duração ou frequência definidas pela balança.';

        $hasPrevious = data_get($context, 'history_comparison.has_previous', false);
        $followUp = ! $hasPrevious
            ? 'Como este é o registro inicial, combine quando repetir a medição em condições semelhantes de hidratação, alimentação e horário; ainda não há tendência individual para interpretar.'
            : (data_get($context, 'history_comparison.automatic_interpretation_allowed')
                ? 'Compare os registros tecnicamente compatíveis com o profissional e relate as condições das medições antes de atribuir significado às diferenças.'
                : 'O histórico tem perfis que não permitem interpretação automática segura. Confira essas diferenças com o profissional antes de interpretar evolução.');
        $followUp .= $approved
            ? ' Use a síntese já revisada para esclarecer objetivos, alimentação, atividades e limitações que não constam na medição; não adote o peso-alvo do equipamento como meta pessoal.'
            : ' Defina os próximos passos com o profissional; não adote o peso-alvo do equipamento como meta pessoal.';

        return [
            $this->claim('nutrition-contextual', 'nutrition_education', $nutrition, [...$nutritionEvidence, ...$approved, 'brazil-food-guide-natural-foods', 'brazil-food-guide-ultraprocessed']),
            $this->claim('movement-contextual', 'movement_education', $movement, [...$movementEvidence, ...$approved, $older ? 'who-older-adult-functional-activity' : 'who-adult-physical-activity', 'vita-scan-care-guidance-safety']),
            $this->claim('professional-follow-up', 'professional_follow_up', $followUp, [$hasPrevious ? 'assessment:history-profile' : 'assessment:current', ...$approved, 'vita-scan-care-guidance-safety']),
        ];
    }

    private function nutritionFocus(?array $bmi, ?array $fat): string
    {
        $classification = mb_strtolower($bmi['classification'] ?? '');
        if (str_contains($classification, 'baixo') || ($fat['classification'] ?? null) === 'low') {
            return 'Não transforme essas faixas em motivo para restringir alimentos. A adequação das refeições depende do histórico e da avaliação nutricional, não de um valor isolado.';
        }
        if (str_contains($classification, 'obesidade')) {
            return 'Essa faixa de IMC não define uma meta alimentar ou restrição de calorias. Discuta a composição e a rotina com o nutricionista antes de adotar o peso-alvo do equipamento.';
        }
        if (str_contains($classification, 'sobrepeso')) {
            return 'IMC e estimativa de gordura descrevem aspectos diferentes: a faixa de sobrepeso, sozinha, não resume sua composição. Conhecer as refeições e preferências é necessário antes de individualizar mudanças.';
        }

        return 'As estimativas não explicam seus hábitos nem definem uma dieta; use a consulta para contextualizar as refeições e preferências antes de individualizar mudanças.';
    }

    private function movementFocus(array $muscle): string
    {
        $classifications = array_unique(array_column($muscle, 'classification'));
        if ($classifications === ['standard']) {
            return 'Todas as regiões registradas estão em Padrão, mas isso não comprova força ou função; a capacidade para as atividades ainda precisa ser avaliada.';
        }
        if (in_array('low', $classifications, true)) {
            return 'A faixa Baixo não confirma perda muscular. Avalie força e mobilidade com o profissional antes de direcionar o fortalecimento a uma região.';
        }
        if (in_array(null, $classifications, true)) {
            return 'Há regiões sem classificação identificada; confira o registro antes de usar as estimativas para discutir atividades. Força e função exigem avaliação própria.';
        }
        if (count($classifications) === 1) {
            return 'A classificação muscular registrada não mede força ou função e não justifica, sozinha, aumentar carga ou presumir condicionamento.';
        }

        return 'Classificações regionais diferentes não medem força ou função e não justificam, sozinhas, treinar mais um membro ou aumentar a carga.';
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
