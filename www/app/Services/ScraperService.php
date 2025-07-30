<?php

namespace App\Services;

class ScraperService {
    private $pythonScriptPath;
    private $actions = [];
    private $currentUrl = null;
    private $sessionStarted = false;

    public function __construct() {
        $this->pythonScriptPath = "/var/src/scraper.py";
        if (!file_exists($this->pythonScriptPath) || !is_executable($this->pythonScriptPath)) {
            throw new \Exception("Il percorso allo script Python non è valido o non è eseguibile: " . $this->pythonScriptPath);
        }
    }

    public function startSession(string $url): self {
        $this->currentUrl = $url;
        $this->actions[] = ['method' => 'openPage', 'params' => ['url' => $url]];
        $this->sessionStarted = true;
        return $this;
    }

    public function openPage(string $url): self {
        $this->currentUrl = $url;
        $this->actions[] = ['method' => 'openPage', 'params' => ['url' => $url]];
        return $this;
    }

    public function clickElement(string $selector): self {
        $this->actions[] = ['method' => 'clickElement', 'params' => ['selector' => $selector]];
        return $this;
    }

    public function typeInField(string $selector, string $text): self {
        $this->actions[] = ['method' => 'typeInField', 'params' => ['selector' => $selector, 'text' => $text]];
        return $this;
    }

    public function waitForElement(string $selector, int $timeout = 10): self {
        $this->actions[] = ['method' => 'waitForElement', 'params' => ['selector' => $selector, 'timeout' => $timeout]];
        return $this;
    }

    public function getContent(string $selector = null, string $attribute = 'text'): self {
        $this->actions[] = ['method' => 'getContent', 'params' => ['selector' => $selector, 'attribute' => $attribute]];
        return $this;
    }

    public function getVideoLinks(): self {
        $this->actions[] = ['method' => 'getVideoLinks', 'params' => []];
        return $this;
    }

    public function getFullHTML(): self {
        $this->actions[] = ['method' => 'getFullHTML', 'params' => []];
        return $this;
    }

    public function closeSession(): self {
        $this->actions[] = ['method' => 'closeSession', 'params' => []];
        return $this;
    }

    public function execute(): ?array {
        if (!$this->sessionStarted && !empty($this->actions)) {
            // Se ci sono azioni ma la sessione non è stata esplicitamente avviata,
            // assumiamo che la prima azione (openPage) imposti l'URL iniziale.
            if (isset($this->actions[0]['method']) && $this->actions[0]['method'] === 'openPage' && isset($this->actions[0]['params']['url'])) {
                $this->currentUrl = $this->actions[0]['params']['url'];
            } else {
                throw new \Exception("Nessuna URL iniziale definita. Usa startSession() o la prima azione deve essere openPage().");
            }
        } elseif (!$this->sessionStarted && empty($this->actions)) {
            return ['status' => 'info', 'message' => 'Nessuna azione definita.'];
        }

        $finalResult = null;
        $comando = "/var/python/venv/bin/python " . escapeshellarg($this->pythonScriptPath) . " " . escapeshellarg($this->currentUrl);
        $options = [];

        // Costruisci le opzioni per lo script Python basate su tutte le azioni
        foreach ($this->actions as $action) {
            $method = $action['method'];
            $params = $action['params'] ?? [];

            switch ($method) {
                case 'openPage':
                    $this->currentUrl = $params['url'];
                    break;
                case 'clickElement':
                    if (!isset($options['clicca'])) $options['clicca'] = [];
                    $options['clicca'][] = $params['selector'];
                    break;
                case 'typeInField':
                    if (!isset($options['digita'])) $options['digita'] = [];
                    $options['digita'][$params['selector']] = $params['text'];
                    break;
                case 'waitForElement':
                    $options['attendi'] = $params['selector'];
                    if (isset($params['timeout'])) $options['timeout'] = $params['timeout'];
                    break;
                case 'getContent':
                    $options['contenuto'] = $params['selector'] ?? null;
                    $options['attributo'] = $params['attribute'] ?? 'text';
                    break;
                case 'getVideoLinks':
                    $options['video_links'] = true;
                    break;
                case 'getFullHTML':
                    $options['contenuto'] = null; // Per ottenere l'HTML completo
                    break;
                case 'closeSession':
                    // Potremmo passare un flag allo script Python se necessario per una chiusura esplicita
                    break;
                // startSession è già gestito all'inizio
            }
        }

        // Costruisci la stringa del comando con tutte le opzioni
        if (isset($options['clicca']) && is_array($options['clicca'])) {
            $comando .= " --click " . implode(" ", array_map('escapeshellarg', $options['clicca']));
        }

        if (isset($options['digita']) && is_array($options['digita'])) {
            foreach ($options['digita'] as $selector => $text) {
                $comando .= " --type " . escapeshellarg($selector) . " " . escapeshellarg($text);
            }
        }

        if (isset($options['attendi'])) {
            $comando .= " --wait " . escapeshellarg($options['attendi']);
        }

        if (isset($options['contenuto'])) {
            $comando .= " --content " . escapeshellarg($options['contenuto']);
        }

        if (isset($options['attributo'])) {
            $comando .= " --attribute " . escapeshellarg($options['attributo']);
        }

        if (isset($options['timeout'])) {
            $comando .= " --timeout " . escapeshellarg($options['timeout']);
        }

        if (isset($options['video_links']) && $options['video_links'] === true) {
            $comando .= " --video_links";
        }

        //dd($comando);
        //putenv('PLAYWRIGHT_BROWSERS_PATH=/root/.cache/ms-playwright');

        $output_json = shell_exec($comando);
        $finalResult = json_decode(trim($output_json), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Errore nella decodifica JSON: " . json_last_error_msg() . " - Output: " . $output_json);
            return ['status' => 'error', 'message' => 'Errore nella decodifica JSON dall\'output Python.', 'raw_output' => $output_json];
        }

        // Reset actions after execution
        $this->actions = [];
        $this->sessionStarted = false;
        $this->currentUrl = null;

        return $finalResult;
    }
}
