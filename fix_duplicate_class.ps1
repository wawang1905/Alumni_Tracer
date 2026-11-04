$files = @(
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\AlumniManagement.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\Events.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\News.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\Surveys.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\Reports.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\Settings.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\AlumniBatchDetails.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\AlumniCollegeDetails.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\AlumniCourseDetails.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\SurveyCreate.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\SurveyResponseDetails.html',
    'c:\xampp\htdocs\Alumni_Tracer\app\Views\HTML\Staff\SurveyAllResponsesDetails.html'
)

foreach ($file in $files) {
    if (Test-Path $file) {
        $content = Get-Content $file -Raw
        
        # Fix duplicate class attributes
        $content = $content -replace 'class="menu-item" data-page="([^"]+)" class="menu-item active"', 'class="menu-item active" data-page="$1"'
        
        $content | Set-Content $file
        Write-Host "Fixed: $file"
    }
}
