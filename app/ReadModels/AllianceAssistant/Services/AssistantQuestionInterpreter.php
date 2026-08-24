<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\Services;

use App\Contexts\GameWorld\Progression\Enums\ProgressionFactKind;
use App\Contexts\GameWorld\Progression\ValueObjects\ProgressionFactRequest;
use App\ReadModels\AllianceAssistant\Enums\AssistantIntent;
use App\ReadModels\AllianceAssistant\Enums\AssistantPrompt;
use App\ReadModels\AllianceAssistant\ValueObjects\ParsedQuestion;

final class AssistantQuestionInterpreter
{
    public function interpret(string $question, ?AssistantPrompt $prompt = null): ParsedQuestion
    {
        $normalized = $this->normalize($question);

        if ($this->looksLikeWrite($normalized)) {
            if (preg_match('/\b(roster|rostered|sign me up|register me)\b/u', $normalized) === 1) {
                return new ParsedQuestion(
                    AssistantIntent::ActionHandoff,
                    $this->eventSubject($normalized),
                    writeAction: 'roster',
                );
            }

            return new ParsedQuestion(AssistantIntent::Unsupported);
        }

        if ($prompt instanceof AssistantPrompt) {
            return $this->fromPrompt($prompt);
        }

        if ($normalized === '' || preg_match('/^(help|what can you (answer|do)|how can you help)$/u', $normalized) === 1) {
            return new ParsedQuestion(AssistantIntent::Help);
        }

        $gameFact = $this->gameFact($normalized);
        if ($gameFact instanceof ProgressionFactRequest) {
            return new ParsedQuestion(AssistantIntent::GameFact, gameFact: $gameFact);
        }

        if (preg_match('/\b(transfer|transferable)\b/u', $normalized) === 1) {
            preg_match('/\bkingdom\s*#?\s*(\d+)\b/u', $normalized, $matches);

            return new ParsedQuestion(
                AssistantIntent::TransferStatusSelf,
                kingdomNumber: isset($matches[1]) ? (int) $matches[1] : null,
            );
        }

        if (preg_match('/\b(rsvp|registered|register|registration|waitlist|waitlisted|response)\b/u', $normalized) === 1) {
            $mode = preg_match('/\bwaitlist|waitlisted\b/u', $normalized) === 1
                ? 'waitlist'
                : (preg_match('/\bregistered|register|registration\b/u', $normalized) === 1 ? 'registration' : 'rsvp');

            return new ParsedQuestion(
                AssistantIntent::EventParticipationSelf,
                $this->eventSubject($normalized),
                thisWeek: str_contains($normalized, 'this week'),
                participationMode: $mode,
            );
        }

        if (preg_match('/\b(objective|assignment|assigned|team)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::BattlePlanSelf,
                $this->eventSubject($normalized),
            );
        }

        if (preg_match('/\b(hive layout|territory plan|territory layout|layout)\b/u', $normalized) === 1) {
            return new ParsedQuestion(
                AssistantIntent::TerritoryPlan,
                $this->eventSubject($normalized),
            );
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

        if (preg_match('/\b(rostered|roster|slot)\b/u', $normalized) === 1) {
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
            AssistantPrompt::Observation => new ParsedQuestion(AssistantIntent::AllianceObservation, 'opponent'),
            AssistantPrompt::HeroFact => new ParsedQuestion(
                AssistantIntent::GameFact,
                gameFact: new ProgressionFactRequest(ProgressionFactKind::HeroGeneration, 'Amadeus'),
            ),
            AssistantPrompt::RsvpWeek => new ParsedQuestion(
                AssistantIntent::EventParticipationSelf,
                thisWeek: true,
                participationMode: 'rsvp',
            ),
            AssistantPrompt::BattleAssignment => new ParsedQuestion(AssistantIntent::BattlePlanSelf, 'Swordland'),
            AssistantPrompt::TransferStatus => new ParsedQuestion(AssistantIntent::TransferStatusSelf),
            AssistantPrompt::TerritoryPlan => new ParsedQuestion(AssistantIntent::TerritoryPlan, 'Bear Hunt'),
        };
    }

    private function gameFact(string $question): ?ProgressionFactRequest
    {
        if (preg_match('/\bwhat generation is\s+(.+?)(?:\?|$)/u', $question, $matches) === 1) {
            return new ProgressionFactRequest(ProgressionFactKind::HeroGeneration, $this->cleanSubject($matches[1]) ?? '');
        }

        if (preg_match('/\bwhat troop class is\s+(.+?)(?:\?|$)/u', $question, $matches) === 1) {
            return new ProgressionFactRequest(ProgressionFactKind::HeroTroopClass, $this->cleanSubject($matches[1]) ?? '');
        }

        if (preg_match('/\bwhat is the max(?:imum)?\s+(.+?)\s+level\b/u', $question, $matches) === 1) {
            return new ProgressionFactRequest(ProgressionFactKind::SystemMaxLevel, $this->cleanSubject($matches[1]) ?? '');
        }

        if (preg_match('/\bwhat does governor gear\s+(.+?)\s+(\d+)\s+require\b/u', $question, $matches) === 1) {
            return new ProgressionFactRequest(
                ProgressionFactKind::GovernorGearRequirement,
                $this->cleanSubject($matches[1]) ?? '',
                (int) $matches[2],
            );
        }

        if (preg_match('/\b(?:stats|statistics)\s+for\s+(infantry|cavalry|lancers?|archers?)\s+(?:troop\s+)?(?:tier\s+)?t?(\d+)\b/u', $question, $matches) === 1
            || preg_match('/\b(?:stats|statistics)\s+for\s+(?:tier\s+)?t?(\d+)\s+(infantry|cavalry|lancers?|archers?)\b/u', $question, $reverse) === 1) {
            if (isset($matches[1], $matches[2])) {
                return new ProgressionFactRequest(ProgressionFactKind::TroopTierStats, $matches[1], (int) $matches[2]);
            }

            return new ProgressionFactRequest(ProgressionFactKind::TroopTierStats, $reverse[2], (int) $reverse[1]);
        }

        if (preg_match('/\bwhat are the (?:stats|statistics) for this troop tier\b/u', $question) === 1) {
            return new ProgressionFactRequest(ProgressionFactKind::TroopTierStats, '');
        }

        if (preg_match('/\bwhat does academy research\s+(.+?)\s+(?:level|lv\.?)[ ]*(\d+)\s+do\b/u', $question, $matches) === 1
            || preg_match('/\bwhat does\s+(.+?)\s+academy research\s+(?:level|lv\.?)[ ]*(\d+)\s+do\b/u', $question, $matches) === 1) {
            return new ProgressionFactRequest(
                ProgressionFactKind::AcademyResearchLevel,
                $this->cleanSubject($matches[1]) ?? '',
                (int) $matches[2],
            );
        }

        if (preg_match('/\bwhat does this academy research level do\b/u', $question) === 1) {
            return new ProgressionFactRequest(ProgressionFactKind::AcademyResearchLevel, '');
        }

        return null;
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
        if (preg_match('/\bfor\s+(.+?)(?:\?|$)/u', $question, $matches) === 1) {
            $candidate = preg_replace('/\bthis week\b/u', ' ', $matches[1]);
            $clean = $this->cleanSubject($candidate);
            if ($clean !== null) {
                return $clean;
            }
        }

        if (preg_match('/\bmy\s+(.+?)\s+(?:assignment|team|roster|slot)\b/u', $question, $matches) === 1) {
            $clean = $this->cleanSubject($matches[1]);
            if ($clean !== null) {
                return $clean;
            }
        }

        $subject = preg_replace([
            '/\bwhat time\b/u',
            '/\bwhen\b/u',
            '/\bdoes\b/u',
            '/\bdid\b/u',
            '/\bdo\b/u',
            '/\bam i\b/u',
            '/\bi am\b/u',
            '/\bis\b/u',
            '/\bstarts?\b/u',
            '/\bstart time\b/u',
            '/\brostered\b/u',
            '/\broster\b/u',
            '/\brsvp\b/u',
            '/\bregistered\b/u',
            '/\bregister\b/u',
            '/\bregistration\b/u',
            '/\bwaitlisted?\b/u',
            '/\bresponse\b/u',
            '/\bobjective\b/u',
            '/\bassignment\b/u',
            '/\bassigned\b/u',
            '/\bteam\b/u',
            '/\bslot\b/u',
            '/\bwhich\b/u',
            '/\bhive\b/u',
            '/\blayout\b/u',
            '/\bterritory\b/u',
            '/\bplan\b/u',
            '/\bare we using\b/u',
            '/\busing\b/u',
            '/\bput me\b/u',
            '/\badd me\b/u',
            '/\broster me\b/u',
            '/\bsign me up\b/u',
            '/\bfor\b/u',
            '/\bon\b/u',
            '/\bmy\b/u',
            '/\bthis week\b/u',
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
        $subject = trim((string) preg_replace('/^the\s+/u', '', $subject));

        return $subject === '' ? null : $subject;
    }

    private function normalize(string $question): string
    {
        $question = mb_strtolower(trim($question));

        return trim((string) preg_replace('/\s+/u', ' ', $question));
    }
}
