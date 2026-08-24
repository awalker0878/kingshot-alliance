<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Services;

use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;

final class AssistantQuestionInterpreter
{
    public function interpret(string $question, ?AssistantPrompt $prompt = null): ParsedQuestion
    {
        $normalized = $this->normalize($question);

        if ($this->looksLikeWrite($normalized)) {
            return new ParsedQuestion(AssistantIntent::Unsupported);
        }

        if ($prompt instanceof AssistantPrompt) {
            return $this->fromPrompt($prompt);
        }

        if ($normalized === '' || preg_match('/^(help|what can you (answer|do)|how can you help)$/u', $normalized) === 1) {
            return new ParsedQuestion(AssistantIntent::Help);
        }

        if (preg_match('/\b(observ(?:e|ed|ation|ations)|intelligence|what do we know)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::AllianceObservation,
                $this->observationSubject($normalized),
            );
        }

        if (preg_match('/\b(guide|strategy|instructions?)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::AllianceContent,
                $this->contentSubject($normalized),
            );
        }

        if (preg_match('/\b(rostered|roster|assignment|assigned|team|slot)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::EventRosterSelf,
                $this->eventSubject($normalized),
                preg_match('/\b(what time|when|starts?|start time)\b/u', $normalized) === 1,
            );
        }

        if (preg_match('/\b(next|upcoming)\s+event\b/u', $normalized) === 1) {
            return new ParsedQuestion(AssistantIntent::EventTime, null, true, true);
        }

        if (preg_match('/\b(what time|when|starts?|start time)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::EventTime,
                $this->eventSubject($normalized),
                true,
            );
        }

        return new ParsedQuestion(AssistantIntent::Unsupported);
    }

    private function fromPrompt(AssistantPrompt $prompt): ParsedQuestion
    {
        return match ($prompt) {
            AssistantPrompt::SwordlandRoster => new ParsedQuestion(
                AssistantIntent::EventRosterSelf,
                'Swordland',
                true,
            ),
            AssistantPrompt::NextEvent => new ParsedQuestion(AssistantIntent::EventTime, null, true, true),
            AssistantPrompt::BearHuntGuide => new ParsedQuestion(AssistantIntent::AllianceContent, 'Bear Hunt'),
        };
    }

    private function looksLikeWrite(string $question): bool
    {
        return preg_match(
            '/\b(put me|add me|assign me|remove me|roster me|sign me up|register me|cancel my|change my|update my|create|delete|archive|publish|send|post)\b/u',
            $question,
        ) === 1;
    }

    private function eventSubject(string $question): ?string
    {
        $subject = preg_replace([
            '/\bwhat time\b/u',
            '/\bwhen\b/u',
            '/\bdoes\b/u',
            '/\bdo\b/u',
            '/\bam i\b/u',
            '/\bi am\b/u',
            '/\bis\b/u',
            '/\bstarts?\b/u',
            '/\bstart time\b/u',
            '/\brostered\b/u',
            '/\broster\b/u',
            '/\bfor\b/u',
            '/\bmy\b/u',
            '/\bassignment\b/u',
            '/\bassigned\b/u',
            '/\bteam\b/u',
            '/\bslot\b/u',
            '/\band\b/u',
        ], ' ', $question);

        return $this->cleanSubject($subject);
    }

    private function contentSubject(string $question): ?string
    {
        if (preg_match('/\bour\s+(.+?)\s+(?:guide|strategy|instructions?)\b/u', $question, $matches) === 1) {
            return $this->cleanSubject($matches[1]);
        }

        if (preg_match('/\b(?:for|about)\s+(.+?)(?:\?|$)/u', $question, $matches) === 1) {
            return $this->cleanSubject($matches[1]);
        }

        $subject = preg_replace(
            '/\b(what|does|do|our|the|guide|say|strategy|are|we|using|use|instructions?|for|about)\b/u',
            ' ',
            $question,
        );

        return $this->cleanSubject($subject);
    }

    private function observationSubject(string $question): ?string
    {
        if (preg_match('/\babout\s+(.+?)(?:\?|$)/u', $question, $matches) === 1) {
            return $this->cleanSubject($matches[1]);
        }

        $subject = preg_replace(
            '/\b(what|have|we|observed|observe|observation|observations|do|know|intelligence|on|the|alliance)\b/u',
            ' ',
            $question,
        );

        return $this->cleanSubject($subject);
    }

    private function cleanSubject(?string $subject): ?string
    {
        $subject = trim((string) preg_replace('/[^\pL\pN\-\s]+/u', ' ', (string) $subject));
        $subject = trim((string) preg_replace('/\s+/u', ' ', $subject));

        return $subject === '' ? null : $subject;
    }

    private function normalize(string $question): string
    {
        $question = mb_strtolower(trim($question));

        return trim((string) preg_replace('/\s+/u', ' ', $question));
    }
}
