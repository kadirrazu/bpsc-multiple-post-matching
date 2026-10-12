<?php

namespace App\Services\Matching;

class MatchEngine
{
    public const EXACT = ['name', 'b_date', 's_roll', 's_year'];

    private array $identities = [];

    private array $exact = [];

    private array $anchors = [];

    public function __construct(array $catalog = [])
    {
        foreach ($catalog as $item) {
            $id = count($this->identities);
            $this->identities[$id] = $item;
            $identity = $item['identity'];
            $this->exact[$this->key($identity)][] = $id;
            foreach ($this->anchors($identity) as $anchor) {
                $this->anchors[$anchor][] = $id;
            }
        }
    }

    private function key(array $row): string
    {
        return json_encode(array_map(fn ($f) => (string) ($row[$f] ?? ''), self::EXACT), JSON_UNESCAPED_UNICODE);
    }

    private function anchors(array $row): array
    {
        $keys = ['dob:'.$row['b_date'], 'ssc:'.$row['s_roll'].'|'.$row['s_year']];
        foreach (['name', 'fname', 'mname'] as $f) {
            $value = trim(preg_replace('/[^\p{L}\p{M}\p{N}]+/u', ' ', mb_strtolower($row[$f] ?? '')));
            foreach (explode(' ', $value) as $word) {
                if (mb_strlen($word) >= 4) {
                    $keys[] = $f.':'.mb_substr($word, 0, 3);
                }
            }
        }
        if (! empty($row['nid_no'])) {
            $keys[] = 'nid:'.$row['nid_no'];
        }
        if (! empty($row['h_roll']) && ! empty($row['h_year'])) {
            $keys[] = 'hsc:'.$row['h_roll'].'|'.$row['h_year'];
        }

        return array_unique($keys);
    }

    public function match(array $row): array
    {
        $exact = [];
        foreach ($this->exact[$this->key($row)] ?? [] as $i) {
            $exact[$this->identities[$i]['person_id']] = ['record_id' => $this->identities[$i]['record_id'], 'reason' => 'Name, DOB and SSC roll/year exact match'];
        }
        if (count($exact) === 1) {
            $person = array_key_first($exact);
            $item = $this->identities[$this->exact[$this->key($row)][0]];
            $c = $item['identity'];
            $conflict = false;
            foreach (['h_roll', 'h_year', 'nid_no'] as $f) {
                if (! empty($row[$f]) && ! empty($c[$f]) && $row[$f] !== $c[$f]) {
                    $conflict = true;
                }
            }
            foreach (['fname', 'mname'] as $f) {
                if (! empty($row[$f]) && ! empty($c[$f]) && $row[$f] !== $c[$f] && ! $this->similar($row[$f], $c[$f])) {
                    $conflict = true;
                }
            }
            if (! $conflict) {
                return ['status' => 'EXACT', 'suggestions' => $exact];
            }
            $exact[$person]['reason'] .= '; optional identity fields differ — review required';
        }
        $found = $exact;
        $pool = [];
        foreach ($this->anchors($row) as $anchor) {
            foreach ($this->anchors[$anchor] ?? [] as $i) {
                $pool[$i] = true;
            }
        }
        foreach (array_keys($pool) as $i) {
            $item = $this->identities[$i];
            $c = $item['identity'];
            $reasons = [];
            $name = $this->similar($row['name'], $c['name']);
            $father = $this->similar($row['fname'] ?? '', $c['fname'] ?? '');
            $mother = $this->similar($row['mname'] ?? '', $c['mname'] ?? '');
            $dob = $row['b_date'] === $c['b_date'];
            $ssc = $row['s_roll'] === $c['s_roll'] && $row['s_year'] === $c['s_year'];
            $nid = ! empty($row['nid_no']) && $row['nid_no'] === ($c['nid_no'] ?? '');
            $hsc = ! empty($row['h_roll']) && ! empty($row['h_year']) && $row['h_roll'] === ($c['h_roll'] ?? '') && $row['h_year'] === ($c['h_year'] ?? '');
            if ($dob && $ssc && ($name || ($father && $mother))) {
                foreach (['Name similarity' => $name, 'Father similarity' => $father, 'Mother similarity' => $mother, 'DOB exact' => $dob, 'SSC roll/year exact' => $ssc, 'NID exact' => $nid, 'HSC roll/year exact' => $hsc] as $label => $yes) {
                    if ($yes) {
                        $reasons[] = $label;
                    }
                }
                if (! isset($found[$item['person_id']])) {
                    $found[$item['person_id']] = ['record_id' => $item['record_id'], 'reason' => implode(', ', $reasons)];
                }
            }
        }
        if (count($found) > 50) {
            throw new \InvalidArgumentException('More than 50 possible matches. Correct or strengthen the identity information.');
        }

        return ['status' => $found ? 'REVIEW' : 'NEW', 'suggestions' => $found];
    }

    public function similar(?string $a, ?string $b): bool
    {
        if (trim((string) $a) !== '' && mb_strtolower(trim((string) $a)) === mb_strtolower(trim((string) $b))) {
            return true;
        }
        $normalize = fn ($v) => trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{M}\p{N}\s]/u', ' ', mb_strtolower((string) $v))));
        $honorifics = ['md', 'mst', 'mosammat', 'most', 'mohammad', 'mohammed', 'muhammad', 'মো', 'মোঃ', 'মোহাম্মদ', 'মুহাম্মদ', 'মোসা', 'মোসাঃ', 'মোছা', 'মোছাঃ', 'মোছাম্মৎ', 'মোসাম্মৎ'];
        $tokens = fn ($value) => array_values(array_diff(array_unique(explode(' ', $normalize($value))), array_merge([''], $honorifics)));
        $left = $tokens($a);
        $right = $tokens($b);
        if (! $left || ! $right) {
            return false;
        }
        $common = ['hossain', 'hosain', 'hussain', 'hossen', 'islam', 'begum', 'ali', 'uddin', 'হোসেন', 'হোসাইন', 'ইসলাম', 'বেগম', 'আলী', 'উদ্দিন'];
        $matched = 0;
        $meaningful = false;
        $used = [];
        // Exact tokens first so fuzzy matches cannot consume another token's exact match.
        foreach ([false, true] as $fuzzy) {
            foreach ($left as $i => $word) {
                if (isset($used['left:'.$i])) {
                    continue;
                }
                foreach ($right as $j => $other) {
                    if (isset($used['right:'.$j])) {
                        continue;
                    }
                    if (! $fuzzy ? $word === $other : $this->minorSpellingDifference($word, $other)) {
                        $used['left:'.$i] = true;
                        $used['right:'.$j] = true;
                        $matched++;
                        if (! in_array($word, $common, true) && ! in_array($other, $common, true)) {
                            $meaningful = true;
                        }
                        break;
                    }
                }
            }
        }
        if (! $meaningful) {
            return false;
        }

        // Shortened names need at least two complete tokens, never a surname substring.
        return $matched / max(count($left), count($right)) >= 0.75
            || ($matched >= 2 && $matched === min(count($left), count($right)));
    }

    private function minorSpellingDifference(string $a, string $b): bool
    {
        // Compare individual words: a shared long surname cannot hide a different given name.
        $x = mb_str_split($a);
        $y = mb_str_split($b);
        $length = max(count($x), count($y));
        if (min(count($x), count($y)) < 4 || abs(count($x) - count($y)) > $length * 0.2) {
            return false;
        }
        $previous = range(0, count($y));
        foreach ($x as $i => $char) {
            $current = [$i + 1];
            foreach ($y as $j => $other) {
                $current[$j + 1] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($char === $other ? 0 : 1));
            }
            $previous = $current;
        }

        return end($previous) / $length <= 0.2;
    }
}
