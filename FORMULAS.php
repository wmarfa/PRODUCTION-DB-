<?php
require_once "config.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Formulas Reference</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <style>
        .formula-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .formula-equation {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 1.1em;
        }
        .variable-table {
            font-size: 0.9em;
        }
        .calculation-example {
            background: #e8f4fd;
            border-left: 4px solid #17a2b8;
        }
        .section-icon {
            font-size: 2em;
            color: #007bff;
            margin-bottom: 15px;
        }
        .score-poor { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .score-good { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .score-excellent { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>
    <?php require_once "navbar.php"; ?>
    
    <div class="container mt-5 mb-5">
        <div class="page-header mb-5 text-center">
            <h1 class="page-title">PERFORMANCE FORMULAS REFERENCE</h1>
            <p class="lead text-muted">Complete guide to all calculations and scoring metrics used in the system</p>
        </div>

        <!-- Quick Navigation -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-bookmark me-2"></i> Quick Navigation
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3 mb-2">
                        <a href="#scoring-system" class="btn btn-outline-primary btn-sm w-100">Scoring System</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="#efficiency-metrics" class="btn btn-outline-primary btn-sm w-100">Efficiency Metrics</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="#manpower-calculations" class="btn btn-outline-primary btn-sm w-100">Manpower Calculations</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="#output-calculations" class="btn btn-outline-primary btn-sm w-100">Output Calculations</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Scoring System -->
        <div class="card mb-5" id="scoring-system">
            <div class="card-header bg-success text-white">
                <div class="section-icon text-center">
                    <i class="fas fa-star"></i>
                </div>
                <h3 class="text-center mb-0">TOTAL PERFORMANCE SCORING SYSTEM</h3>
            </div>
            <div class="card-body">
                <div class="formula-equation text-center mb-4">
                    <strong>Total Score = ARS + SRS + PCS + CPHS</strong><br>
                    <small class="text-muted">Where: ARS + SRS + PCS + CPHS = 100 points maximum</small>
                </div>

                <!-- Absent Rate Score -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-primary">
                        <i class="fas fa-user-slash me-2"></i>1. Absent Rate Score (ARS) - Maximum: 30 points
                    </h4>
                    <div class="formula-equation mb-3">
                        <strong>IF Absent Rate ≤ 5%:</strong> ARS = (1 - Absent Rate) × 30<br>
                        <strong>IF Absent Rate > 5%:</strong> ARS = MAX(0, (0.7 - Absent Rate) × 30)
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Absent Rate</strong></td>
                                    <td>Percentage of absent manpower</td>
                                    <td>Absent MP ÷ Total MP</td>
                                </tr>
                                <tr>
                                    <td><strong>ARS</strong></td>
                                    <td>Absent Rate Score</td>
                                    <td>0 to 30 points</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> Total MP = 50, Absent MP = 3</p>
                        <p class="mb-1"><strong>Absent Rate:</strong> (3 ÷ 50) = 0.06 (6%)</p>
                        <p class="mb-0"><strong>ARS:</strong> MAX(0, (0.7 - 0.06) × 30) = MAX(0, 0.64 × 30) = <strong>19.2 points</strong></p>
                    </div>
                </div>

                <!-- Separation Rate Score -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-primary">
                        <i class="fas fa-exchange-alt me-2"></i>2. Separation Rate Score (SRS) - Maximum: 30 points
                    </h4>
                    <div class="formula-equation mb-3">
                        <strong>IF Separation Rate = 0%:</strong> SRS = 30<br>
                        <strong>IF Separation Rate > 0%:</strong> SRS = MAX(0, (0.5 - Separation Rate) × 30)
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Example</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Separation Rate</strong></td>
                                    <td>Percentage of separated manpower</td>
                                    <td>Separated MP ÷ Total MP</td>
                                </tr>
                                <tr>
                                    <td><strong>SRS</strong></td>
                                    <td>Separation Rate Score</td>
                                    <td>0 to 30 points</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> Total MP = 50, Separated MP = 1</p>
                        <p class="mb-1"><strong>Separation Rate:</strong> (1 ÷ 50) = 0.02 (2%)</p>
                        <p class="mb-0"><strong>SRS:</strong> MAX(0, (0.5 - 0.02) × 30) = MAX(0, 0.48 × 30) = <strong>14.4 points</strong></p>
                    </div>
                </div>

                <!-- Plan Completion Score -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-primary">
                        <i class="fas fa-bullseye me-2"></i>3. Plan Completion Score (PCS) - Maximum: 20 points
                    </h4>
                    <div class="formula-equation mb-3">
                        PCS = Plan Completion Percentage × 20
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Formula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Plan Completion %</strong></td>
                                    <td>Percentage of plan achieved</td>
                                    <td>(Total ASSY Output ÷ Plan) × 100</td>
                                </tr>
                                <tr>
                                    <td><strong>PCS</strong></td>
                                    <td>Plan Completion Score</td>
                                    <td>0 to 20 points</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> Plan = 1000 units, Actual Output = 950 units</p>
                        <p class="mb-1"><strong>Plan Completion:</strong> (950 ÷ 1000) = 0.95 (95%)</p>
                        <p class="mb-0"><strong>PCS:</strong> 0.95 × 20 = <strong>19.0 points</strong></p>
                    </div>
                </div>

                <!-- CPH Score -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-primary">
                        <i class="fas fa-tachometer-alt me-2"></i>4. CPH Score (CPHS) - Maximum: 20 points
                    </h4>
                    <div class="formula-equation mb-3">
                        CPHS = (Current CPH ÷ Max CPH of the day) × 20
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Formula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Current CPH</strong></td>
                                    <td>Circuits Per Hour for current record</td>
                                    <td>Total Circuit Output ÷ Used MHR</td>
                                </tr>
                                <tr>
                                    <td><strong>Max CPH</strong></td>
                                    <td>Maximum CPH among all records for the day</td>
                                    <td>MAX(All daily CPH values)</td>
                                </tr>
                                <tr>
                                    <td><strong>CPHS</strong></td>
                                    <td>CPH Score</td>
                                    <td>0 to 20 points</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> Current CPH = 45.3, Max Daily CPH = 48.7</p>
                        <p class="mb-0"><strong>CPHS:</strong> (45.3 ÷ 48.7) × 20 = 0.93 × 20 = <strong>18.6 points</strong></p>
                    </div>
                </div>

                <!-- Total Score Summary -->
                <div class="alert alert-info">
                    <h5><i class="fas fa-chart-line me-2"></i>Total Score Calculation Summary</h5>
                    <p class="mb-2">Using the examples above:</p>
                    <div class="row text-center">
                        <div class="col-md-3">
                            <strong>ARS:</strong> 19.2 pts
                        </div>
                        <div class="col-md-3">
                            <strong>SRS:</strong> 14.4 pts
                        </div>
                        <div class="col-md-3">
                            <strong>PCS:</strong> 19.0 pts
                        </div>
                        <div class="col-md-3">
                            <strong>CPHS:</strong> 18.6 pts
                        </div>
                    </div>
                    <hr>
                    <h5 class="text-center mb-0">
                        <strong>TOTAL SCORE:</strong> 19.2 + 14.4 + 19.0 + 18.6 = <strong class="text-success">71.2 points</strong>
                    </h5>
                </div>
            </div>
        </div>

        <!-- Efficiency Metrics -->
        <div class="card mb-5" id="efficiency-metrics">
            <div class="card-header bg-warning text-dark">
                <div class="section-icon text-center">
                    <i class="fas fa-cogs"></i>
                </div>
                <h3 class="text-center mb-0">EFFICIENCY METRICS</h3>
            </div>
            <div class="card-body">

                <!-- ASSY Efficiency -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-warning">
                        <i class="fas fa-industry me-2"></i>ASSY Efficiency
                    </h4>
                    <div class="formula-equation mb-3">
                        ASSY Efficiency = (Total ASSY Output MHR ÷ Used MHR) × 100
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Formula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total ASSY Output MHR</strong></td>
                                    <td>Sum of (ASSY Output × Product MHR)</td>
                                    <td>Σ(assy_output × mhr)</td>
                                </tr>
                                <tr>
                                    <td><strong>Used MHR</strong></td>
                                    <td>Total man-hours utilized</td>
                                    <td>(No OT MP × 7.66) + (OT MP × OT Hours)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> No OT MP = 40, OT MP = 5, OT Hours = 2.0</p>
                        <p class="mb-1"><strong>Used MHR:</strong> (40 × 7.66) + (5 × 2.0) = 306.4 + 10 = <strong>316.4 hours</strong></p>
                    </div>
                </div>

                <!-- Packing Efficiency -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-warning">
                        <i class="fas fa-box me-2"></i>Packing Efficiency
                    </h4>
                    <div class="formula-equation mb-3">
                        Packing Efficiency = (Total Packing Output MHR ÷ Used MHR) × 100
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Formula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total Packing Output MHR</strong></td>
                                    <td>Sum of (Packing Output × Product MHR)</td>
                                    <td>Σ(packing_output × mhr)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CPH Calculation -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-warning">
                        <i class="fas fa-bolt me-2"></i>Circuits Per Hour (CPH)
                    </h4>
                    <div class="formula-equation mb-3">
                        CPH = Total Circuit Output ÷ Used MHR
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Formula</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total Circuit Output</strong></td>
                                    <td>Sum of (ASSY Output × Product Circuit)</td>
                                    <td>Σ(assy_output × circuit)</td>
                                </tr>
                                <tr>
                                    <td><strong>Circuit</strong></td>
                                    <td>Circuit value from product table</td>
                                    <td>Product-specific value</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Plan Completion -->
                <div class="formula-card p-4">
                    <h4 class="text-warning">
                        <i class="fas fa-target me-2"></i>Plan Completion Percentage
                    </h4>
                    <div class="formula-equation mb-3">
                        Plan Completion = (Total ASSY Output ÷ Daily Plan) × 100
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Total ASSY Output</strong></td>
                                    <td>Sum of all ASSY outputs for the day</td>
                                </tr>
                                <tr>
                                    <td><strong>Daily Plan</strong></td>
                                    <td>Planned production target</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Manpower Calculations -->
        <div class="card mb-5" id="manpower-calculations">
            <div class="card-header bg-info text-white">
                <div class="section-icon text-center">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-center mb-0">MANPOWER CALCULATIONS</h3>
            </div>
            <div class="card-body">

                <!-- Used MHR -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-info">
                        <i class="fas fa-clock me-2"></i>Used Man-Hours (Used MHR) - REVISED
                    </h4>
                    <div class="formula-equation mb-3">
                        Used MHR = (No OT MP × 7.66) + (OT MP × OT Hours)
                    </div>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Variable</th>
                                    <th>Description</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>No OT MP</strong></td>
                                    <td>Manpower working standard hours</td>
                                    <td>Variable</td>
                                </tr>
                                <tr>
                                    <td><strong>Standard Hours</strong></td>
                                    <td>Fixed working hours per person</td>
                                    <td><strong>7.66 hours</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>OT MP</strong></td>
                                    <td>Manpower working overtime</td>
                                    <td>Variable</td>
                                </tr>
                                <tr>
                                    <td><strong>OT Hours</strong></td>
                                    <td>Overtime hours worked</td>
                                    <td>Variable</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="calculation-example p-3 mt-3">
                        <h6><i class="fas fa-calculator me-2"></i>Calculation Example:</h6>
                        <p class="mb-1"><strong>Scenario:</strong> No OT MP = 35, OT MP = 8, OT Hours = 1.5</p>
                        <p class="mb-1"><strong>Standard Hours:</strong> 35 × 7.66 = 268.1 hours</p>
                        <p class="mb-1"><strong>Overtime Hours:</strong> 8 × 1.5 = 12.0 hours</p>
                        <p class="mb-0"><strong>Total Used MHR:</strong> 268.1 + 12.0 = <strong>280.1 hours</strong></p>
                    </div>
                </div>

                <!-- Rate Calculations -->
                <div class="formula-card p-4">
                    <h4 class="text-info">
                        <i class="fas fa-percentage me-2"></i>Rate Calculations
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Absent Rate</strong><br>
                                (Absent MP ÷ Total MP) × 100
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Separation Rate</strong><br>
                                (Separated MP ÷ Total MP) × 100
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="formula-equation">
                                <strong>Effective MP</strong><br>
                                Total MP - Absent MP
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="formula-equation">
                                <strong>Available MP</strong><br>
                                Effective MP - Separated MP
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Output Calculations -->
        <div class="card mb-5" id="output-calculations">
            <div class="card-header bg-secondary text-white">
                <div class="section-icon text-center">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <h3 class="text-center mb-0">OUTPUT CALCULATIONS</h3>
            </div>
            <div class="card-body">

                <!-- Total Calculations -->
                <div class="formula-card p-4 mb-4">
                    <h4 class="text-secondary">
                        <i class="fas fa-calculator me-2"></i>Total Output Calculations
                    </h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Total ASSY Output</strong><br>
                                Σ(assy_output)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Total Packing Output</strong><br>
                                Σ(packing_output)
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Total ASSY MHR</strong><br>
                                Σ(assy_output × mhr)
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="formula-equation mb-3">
                                <strong>Total Packing MHR</strong><br>
                                Σ(packing_output × mhr)
                            </div>
                        </div>
                    </div>
                    <div class="formula-equation">
                        <strong>Total Circuit Output</strong><br>
                        Σ(assy_output × circuit)
                    </div>
                </div>

                <!-- Product-based Calculations -->
                <div class="formula-card p-4">
                    <h4 class="text-secondary">
                        <i class="fas fa-cube me-2"></i>Product-based Calculations
                    </h4>
                    <div class="variable-table">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr class="table-light">
                                    <th>Calculation</th>
                                    <th>Formula</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Product MHR Contribution</strong></td>
                                    <td>output × mhr</td>
                                    <td>Efficiency calculation</td>
                                </tr>
                                <tr>
                                    <td><strong>Product Circuit Contribution</strong></td>
                                    <td>output × circuit</td>
                                    <td>CPH calculation</td>
                                </tr>
                                <tr>
                                    <td><strong>Total Output per Product</strong></td>
                                    <td>Σ(output) by product</td>
                                    <td>Production analysis</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score Interpretation -->
        <div class="card">
            <div class="card-header bg-dark text-white">
                <i class="fas fa-graduation-cap me-2"></i> Score Interpretation Guide - CORRECTED
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded score-poor">
                            <h4>Below 80</h4>
                            <strong>Needs Improvement</strong>
                            <p class="small mb-0">Requires attention and corrective actions</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded score-good">
                            <h4>80-89</h4>
                            <strong>Good</strong>
                            <p class="small mb-0">Solid and acceptable performance</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="p-3 rounded score-excellent">
                            <h4>90+</h4>
                            <strong>Excellent</strong>
                            <p class="small mb-0">Outstanding performance</p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Note:</strong> The scoring ranges have been updated to reflect the current performance standards. 
                    Scores below 80 indicate areas needing improvement, while scores of 90 and above represent excellent performance.
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-5">
            <a href="dashboard.php" class="btn btn-primary btn-lg me-3">
                <i class="fas fa-tachometer-alt me-2"></i> Back to Dashboard
            </a>
            <a href="view_data.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-database me-2"></i> View Data
            </a>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="mb-3">Performance Tracking System</h5>
                    <p class="text-light">Operational oversight and data management solution for manufacturing performance tracking.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Performance Tracking System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>