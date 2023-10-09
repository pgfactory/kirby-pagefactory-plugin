[CmdletBinding()]
Param(
    [Parameter(
        Mandatory=$true,
        ValueFromPipeline=$true,
        ValueFromPipelineByPropertyName=$true,
        HelpMessage="The path where to install."
    )][String]$Path,
    [Parameter(
        Mandatory=$false,
        ValueFromPipeline=$true,
        ValueFromPipelineByPropertyName=$true,
        HelpMessage="The branch to use (optional)."
    )][String]$Branch=""
)

If (-not ($Path.Equals("."))) {
    If (-not (Test-Path $Path -PathType Container)) {
        New-Item -Path $Path -ItemType Directory
    }
    If (-not (Test-Path $Path -PathType Container)) {
        Write-Host "Target folder could not be created"
        Write-Host ""
        exit
    }
    Set-Location $Path
}

$DirectoryInfo = Get-ChildItem $Path | Measure-Object
If (-not ($DirectoryInfo.Count.Equals(0)) -and -not (Test-Path "$($Path)\kirby")) {
    Write-Host "Not empty - to install Kirby, folder must be empty"
    exit
}

$CurrentDirectory = Get-Location
## Check/clone Kirby plainkit:
If (-not (Test-Path "$($CurrentDirectory)\kirby")) {
    Write-Host "Now installing Kirby to folder -> $($CurrentDirectory)"
    git clone "https://github.com/getkirby/plainkit.git" .
    Write-Host "Kirby installed"
} Else {
    Write-Host "Kirby already installed"
}

## select the branch you want to check out:
If (-not ($Branch.Equals(""))) {
    $Branch = "-b " + $Branch
}

## Check pagefactory:
If (Test-Path "$($CurrentDirectory)\site\plugins\pagefactory") {
    Write-Host "Pagefactory already installed"
    Write-Host ""
    exit
}

Write-Host ""
Write-Host "Now installing Pagefactory"

## Clone PageFactory:
Write-Host ""
Write-Host "Kirby-Twig:"
git submodule add "https://github.com/wearejust/kirby-twig.git" "site/plugins/kirby-twig"
Set-Location "$($CurrentDirectory)\site\plugins\kirby-twig"
composer update

Set-Location $CurrentDirectory
Write-Host ""
Write-Host "MarkdownPlus:"
git submodule add "https://github.com/pgfactory/markdownplus.git" "site/plugins/markdownplus"

Write-Host ""
Write-Host "PageFactory:"
git submodule add $Branch "https://github.com/pgfactory/kirby-pagefactory-plugin.git" "site/plugins/pagefactory"
Set-Location "$($CurrentDirectory)\sites\plugins\pagefactory"
composer update

Set-Location $CurrentDirectory

Write-Host ""
Write-Host "PageFactory installed"

## Check/copy essential files to final location:
If (-not (Test-Path "$($CurrentDirectory)\site\templates\page_template.html")) {
    Copy-Item -Path "$($CurrentDirectory)\site\plugins\pagefactory\install\content" -Destination "$($CurrentDirectory)\content" -Recurse
    Copy-Item -Path "$($CurrentDirectory)\site\plugins\pagefactory\install\site" -Destination "$($CurrentDirectory)\site" -Recurse
    Move-Item -Path "$($CurrentDirectory)\content\home\home.txt" -Destination "$($CurrentDirectory)\content\home\z.txt"
    Move-Item -Path "$($CurrentDirectory)\content\home" -Destination "$($CurrentDirectory)\content\1_home"
    Write-Host "Essential files copied to final location"
}

Write-Host ""
Write-Host "==> Now open this website in your browser."
Write-Host ""
