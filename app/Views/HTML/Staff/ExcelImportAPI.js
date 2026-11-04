/**
 * Excel Import API Integration
 * Handles Excel file upload with duplicate column detection and processing
 */

// API Configuration
const EXCEL_API_BASE = 'http://localhost:5000/api';

class ExcelImportAPI {
    constructor() {
        this.apiBase = EXCEL_API_BASE;
        this.currentFile = null;
        this.processedData = null;
    }

    async previewExcelFile(file) {
        try {
            const formData = new FormData();
            formData.append('file', file);

            const response = await fetch(`${this.apiBase}/excel/preview`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                this.currentFile = file;
                return result;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error previewing Excel file:', error);
            throw error;
        }
    }

    async uploadExcelFile(file, strategy = 'first') {
        try {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('strategy', strategy);

            const response = await fetch(`${this.apiBase}/excel/upload`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (result.success) {
                this.processedData = result.data;
                return result;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error uploading Excel file:', error);
            throw error;
        }
    }

    async importProcessedData(data) {
        try {
            const response = await fetch(`${this.apiBase}/excel/import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ data: data })
            });

            const result = await response.json();
            
            if (result.success) {
                return result;
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            console.error('Error importing data:', error);
            throw error;
        }
    }

    async checkAPIHealth() {
        try {
            const response = await fetch(`${this.apiBase}/health`);
            const result = await response.json();
            return result.status === 'healthy';
        } catch (error) {
            console.error('Error checking API health:', error);
            return false;
        }
    }
}

// Initialize Excel Import API
const excelImportAPI = new ExcelImportAPI();

// UI Functions for Excel Import
class ExcelImportUI {
    constructor() {
        this.currentStep = 1;
        this.previewData = null;
        this.processedData = null;
        this.setupEventListeners();
    }

    setupEventListeners() {
        // File input change
        const fileInput = document.getElementById('excelFileInput');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        }

        // Strategy selection
        const strategySelect = document.getElementById('duplicateStrategy');
        if (strategySelect) {
            strategySelect.addEventListener('change', (e) => this.handleStrategyChange(e));
        }

        // Import buttons
        const previewBtn = document.getElementById('previewExcelBtn');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => this.previewFile());
        }

        const processBtn = document.getElementById('processExcelBtn');
        if (processBtn) {
            processBtn.addEventListener('click', () => this.processFile());
        }

        const importBtn = document.getElementById('importDataBtn');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.importData());
        }
    }

    handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            this.currentFile = file;
            this.updateFileInfo(file);
            this.enablePreviewButton();
        }
    }

    updateFileInfo(file) {
        const fileInfo = document.getElementById('fileInfo');
        if (fileInfo) {
            fileInfo.innerHTML = `
                <div class="file-selected">
                    <i class="fas fa-file-excel"></i>
                    <span>${file.name}</span>
                    <span class="file-size">(${this.formatFileSize(file.size)})</span>
                </div>
            `;
        }
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    enablePreviewButton() {
        const previewBtn = document.getElementById('previewExcelBtn');
        if (previewBtn) {
            previewBtn.disabled = false;
            previewBtn.classList.remove('disabled');
        }
    }

    async previewFile() {
        if (!this.currentFile) {
            this.showError('Please select a file first');
            return;
        }

        this.showLoading('Previewing file...');

        try {
            const result = await excelImportAPI.previewExcelFile(this.currentFile);
            this.previewData = result;
            this.displayPreview(result);
            this.showStep(2);
        } catch (error) {
            this.showError('Error previewing file: ' + error.message);
        }
    }

    displayPreview(result) {
        const previewContainer = document.getElementById('excelPreview');
        if (!previewContainer) return;

        let duplicateColumnsHtml = '';
        if (result.duplicate_columns && Object.keys(result.duplicate_columns).length > 0) {
            duplicateColumnsHtml = `
                <div class="duplicate-columns-warning">
                    <h4><i class="fas fa-exclamation-triangle"></i> Duplicate Columns Detected</h4>
                    <ul>
                        ${Object.entries(result.duplicate_columns).map(([name, cols]) => 
                            `<li><strong>${name}:</strong> ${cols.join(', ')}</li>`
                        ).join('')}
                    </ul>
                </div>
            `;
        }

        previewContainer.innerHTML = `
            <div class="preview-section">
                <h3>File Preview</h3>
                <div class="preview-stats">
                    <span><strong>Total Rows:</strong> ${result.total_rows_preview}</span>
                    <span><strong>Columns:</strong> ${result.columns.length}</span>
                </div>
                
                ${duplicateColumnsHtml}
                
                <div class="duplicate-strategy">
                    <label for="duplicateStrategy">Duplicate Column Strategy:</label>
                    <select id="duplicateStrategy" class="form-control">
                        <option value="first">Keep First Occurrence</option>
                        <option value="last">Keep Last Occurrence</option>
                        <option value="merge">Merge Columns (Keep First Non-Empty)</option>
                    </select>
                </div>
                
                <div class="preview-table">
                    <h4>Sample Data (First 5 rows)</h4>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                ${result.columns.map(col => `<th>${col}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${result.preview.map(row => 
                                `<tr>
                                    ${result.columns.map(col => `<td>${row[col] || ''}</td>`).join('')}
                                </tr>`
                            ).join('')}
                        </tbody>
                    </table>
                </div>
                
                <div class="preview-actions">
                    <button id="processExcelBtn" class="btn btn-primary">
                        <i class="fas fa-cogs"></i> Process File
                    </button>
                </div>
            </div>
        `;
    }

    async processFile() {
        if (!this.currentFile) {
            this.showError('Please select a file first');
            return;
        }

        const strategy = document.getElementById('duplicateStrategy')?.value || 'first';
        this.showLoading('Processing file...');

        try {
            const result = await excelImportAPI.uploadExcelFile(this.currentFile, strategy);
            this.processedData = result.data;
            this.displayProcessingResult(result);
            this.showStep(3);
        } catch (error) {
            this.showError('Error processing file: ' + error.message);
        }
    }

    displayProcessingResult(result) {
        const resultContainer = document.getElementById('processingResult');
        if (!resultContainer) return;

        const statistics = result.statistics;
        
        resultContainer.innerHTML = `
            <div class="processing-result">
                <h3>Processing Complete</h3>
                
                <div class="result-stats">
                    <div class="stat-item">
                        <span class="stat-number">${statistics.total_rows}</span>
                        <span class="stat-label">Total Records</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${statistics.duplicate_rows_removed}</span>
                        <span class="stat-label">Duplicate Rows Removed</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">${Object.keys(statistics.duplicate_columns).length}</span>
                        <span class="stat-label">Duplicate Columns Found</span>
                    </div>
                </div>
                
                <div class="processing-report">
                    <h4>Processing Report</h4>
                    <pre class="report-text">${result.report}</pre>
                </div>
                
                <div class="import-actions">
                    <button id="importDataBtn" class="btn btn-success">
                        <i class="fas fa-database"></i> Import to Database
                    </button>
                    <button id="downloadProcessedBtn" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Download Processed Data
                    </button>
                </div>
            </div>
        `;
    }

    async importData() {
        if (!this.processedData) {
            this.showError('No processed data available');
            return;
        }

        this.showLoading('Importing data to database...');

        try {
            const result = await excelImportAPI.importProcessedData(this.processedData);
            this.displayImportResult(result);
            this.showStep(4);
        } catch (error) {
            this.showError('Error importing data: ' + error.message);
        }
    }

    displayImportResult(result) {
        const importContainer = document.getElementById('importResult');
        if (!importContainer) return;

        let errorsHtml = '';
        if (result.errors && result.errors.length > 0) {
            errorsHtml = `
                <div class="import-errors">
                    <h4><i class="fas fa-exclamation-circle"></i> Import Errors</h4>
                    <ul>
                        ${result.errors.slice(0, 10).map(error => `<li>${error}</li>`).join('')}
                        ${result.errors.length > 10 ? `<li>... and ${result.errors.length - 10} more errors</li>` : ''}
                    </ul>
                </div>
            `;
        }

        importContainer.innerHTML = `
            <div class="import-result">
                <h3>Import Complete</h3>
                
                <div class="import-stats">
                    <div class="stat-item success">
                        <span class="stat-number">${result.imported_count}</span>
                        <span class="stat-label">Successfully Imported</span>
                    </div>
                    <div class="stat-item ${result.errors.length > 0 ? 'warning' : 'success'}">
                        <span class="stat-number">${result.total_processed - result.imported_count}</span>
                        <span class="stat-label">Failed/Skipped</span>
                    </div>
                </div>
                
                <div class="import-message">
                    <div class="alert ${result.errors.length > 0 ? 'alert-warning' : 'alert-success'}">
                        ${result.message}
                    </div>
                </div>
                
                ${errorsHtml}
                
                <div class="import-actions">
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Refresh Dashboard
                    </button>
                    <button onclick="window.close()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `;
    }

    showStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.import-step').forEach(step => {
            step.style.display = 'none';
        });

        // Show current step
        const currentStep = document.getElementById(`step${stepNumber}`);
        if (currentStep) {
            currentStep.style.display = 'block';
        }

        // Update progress indicator
        this.updateProgress(stepNumber);
        this.currentStep = stepNumber;
    }

    updateProgress(currentStep) {
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((step, index) => {
            const stepNumber = index + 1;
            step.classList.remove('active', 'completed');
            
            if (stepNumber < currentStep) {
                step.classList.add('completed');
            } else if (stepNumber === currentStep) {
                step.classList.add('active');
            }
        });
    }

    showLoading(message) {
        const loadingContainer = document.getElementById('loadingMessage');
        if (loadingContainer) {
            loadingContainer.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <span>${message}</span>
                </div>
            `;
            loadingContainer.style.display = 'block';
        }
    }

    hideLoading() {
        const loadingContainer = document.getElementById('loadingMessage');
        if (loadingContainer) {
            loadingContainer.style.display = 'none';
        }
    }

    showError(message) {
        const errorContainer = document.getElementById('errorMessage');
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
            errorContainer.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                errorContainer.style.display = 'none';
            }, 5000);
        }
    }

    handleStrategyChange(event) {
        // Re-process file if strategy changes and we have a file
        if (this.currentFile && this.previewData) {
            this.processFile();
        }
    }
}

// Initialize Excel Import UI when page loads
let excelImportUI;

document.addEventListener('DOMContentLoaded', () => {
    excelImportUI = new ExcelImportUI();
    
    // Check API health on page load
    excelImportAPI.checkAPIHealth().then(isHealthy => {
        if (!isHealthy) {
            excelImportUI.showError('Excel import API is not available. Please start the Python API server.');
        }
    });
});

// Export for use in other scripts
window.ExcelImportAPI = ExcelImportAPI;
window.ExcelImportUI = ExcelImportUI;
window.excelImportAPI = excelImportAPI;
window.excelImportUI = excelImportUI;
