param(
    [Parameter(Mandatory=$true)][string]$Scenario,
    [Parameter(Mandatory=$true)][string]$OutputDirectory
)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Speech
$demoScenario = Get-Content -LiteralPath $Scenario -Raw -Encoding UTF8 | ConvertFrom-Json
$demoSpeech = New-Object System.Speech.Synthesis.SpeechSynthesizer
try {
    $demoSpeech.SelectVoice($demoScenario.voice)
    $demoSpeech.Rate = [int]$demoScenario.voice_rate
    foreach ($demoScene in $demoScenario.scenes) {
        for ($demoPhraseIndex = 0; $demoPhraseIndex -lt $demoScene.speech.Count; $demoPhraseIndex++) {
            $demoOutput = Join-Path $OutputDirectory ('{0}-{1}.wav' -f $demoScene.id, $demoPhraseIndex)
            $demoSpeech.SetOutputToWaveFile($demoOutput)
            $demoText = [System.Security.SecurityElement]::Escape([string]$demoScene.speech[$demoPhraseIndex])
            $demoText = $demoText.Replace('Dolibarr', '<sub alias="Doli barre">Dolibarr</sub>')
            $demoText = $demoText.Replace('Quartix', '<sub alias="quartixe">Quartix</sub>')
            $demoText = $demoText.Replace('PDF', '<sub alias="pé dé effe">PDF</sub>')
            $demoText = $demoText.Replace('ZIP', '<sub alias="zip">ZIP</sub>')
            $demoSpeech.SpeakSsml('<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="fr-FR">' + $demoText + '</speak>')
            $demoSpeech.SetOutputToNull()
        }
    }
} finally {
    $demoSpeech.Dispose()
}
