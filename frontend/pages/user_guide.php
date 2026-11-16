<?php
$page_title = "User Guide";

require_once "../../backend/includes/header.php";

$role = $_SESSION["role"] ?? "Viewer";

$role_guides = [
    "Administrator" => [
        "title" => "Administrator Quick Guide",
        "intro" => "You can manage users, asset records, and all asset transactions.",
        "steps" => [
            ["title" => "Set up users", "text" => "Add or update employee accounts and assign the correct role.", "href" => "/st/frontend/pages/users.php", "button" => "Open Users"],
            ["title" => "Create asset records", "text" => "Create a category, add each asset with complete details, and use Choose Images or the mobile Take Photo control for photos.", "href" => "/st/frontend/pages/assets.php", "button" => "Open Assets"],
            ["title" => "Enter old assets", "text" => "Use Add Asset and select Asset Source = Legacy / Existing when entering an old company asset.", "href" => "/st/frontend/pages/assets.php", "button" => "Open Assets"],
            ["title" => "Process transactions", "text" => "Use Assignments, Transfers, and Turnovers to update accountability.", "href" => "/st/frontend/pages/assignments.php", "button" => "Open Assignments"],
            ["title" => "Track maintenance", "text" => "Open a work order for an unassigned asset, record service progress and costs, then complete or cancel the order.", "href" => "/st/frontend/pages/maintenance.php", "button" => "Open Maintenance"],
            ["title" => "Check clearance", "text" => "Review employee accountability before printing a clearance form.", "href" => "/st/frontend/pages/clearance.php", "button" => "Open Clearance"]
        ]
    ],
    "Asset Custodian" => [
        "title" => "Asset Custodian Quick Guide",
        "intro" => "You maintain asset records and handle asset assignments, transfers, and returns.",
        "steps" => [
            ["title" => "Register an asset", "text" => "Check the category and asset code, add the asset details, and use Choose Images or the mobile Take Photo control for photos.", "href" => "/st/frontend/pages/assets.php", "button" => "Open Assets"],
            ["title" => "Enter an old asset", "text" => "Use Add Asset and select Asset Source = Legacy / Existing for existing assets and record their current condition and previous reference if available.", "href" => "/st/frontend/pages/assets.php", "button" => "Open Assets"],
            ["title" => "Assign an asset", "text" => "Use Assignment when an available asset is issued to an active employee.", "href" => "/st/frontend/pages/assignment_add.php", "button" => "Assign Asset"],
            ["title" => "Transfer an asset", "text" => "Use Transfer when accountability moves to another employee.", "href" => "/st/frontend/pages/transfers.php", "button" => "Open Transfers"],
            ["title" => "Receive a returned asset", "text" => "Use Turnover when an employee returns an assigned asset.", "href" => "/st/frontend/pages/turnovers.php", "button" => "Open Turnovers"],
            ["title" => "Manage maintenance", "text" => "Create a work order, update its status and service details, and record labor and parts costs.", "href" => "/st/frontend/pages/maintenance.php", "button" => "Open Maintenance"]
        ]
    ],
    "HR / Clearance Officer" => [
        "title" => "HR / Clearance Officer Quick Guide",
        "intro" => "You review employee accountability and prepare clearance documents.",
        "steps" => [
            ["title" => "Find the employee", "text" => "Open Clearance and search for the employee's name or ID.", "href" => "/st/frontend/pages/clearance.php", "button" => "Open Clearance"],
            ["title" => "Check accountable assets", "text" => "Review the number and details of assets still assigned to the employee.", "href" => "/st/frontend/pages/clearance.php", "button" => "Review Records"],
            ["title" => "Coordinate unresolved items", "text" => "Ask the Asset Custodian to process a turnover or transfer when needed.", "href" => "/st/frontend/pages/history.php", "button" => "View History"],
            ["title" => "Print clearance", "text" => "Print the clearance form after the employee's accountability is resolved.", "href" => "/st/frontend/pages/clearance.php", "button" => "Print Clearance"]
        ]
    ],
    "Viewer" => [
        "title" => "Viewer Quick Guide",
        "intro" => "You can view asset records, categories, and transaction history.",
        "steps" => [
            ["title" => "Find an asset", "text" => "Search by asset code, name, serial number, plate number, or supplier.", "href" => "/st/frontend/pages/assets.php", "button" => "Browse Assets"],
            ["title" => "Open asset details", "text" => "Select View to check the asset's complete information.", "href" => "/st/frontend/pages/assets.php", "button" => "View Assets"],
            ["title" => "Review history", "text" => "Use History to see previous assignments, transfers, and returns.", "href" => "/st/frontend/pages/history.php", "button" => "View History"],
            ["title" => "Request a change", "text" => "Contact an Administrator or Asset Custodian when an update is needed.", "href" => "/st/frontend/pages/assets.php", "button" => "View Records"]
        ]
    ]
];

$guide = $role_guides[$role] ?? $role_guides["Viewer"];

$role_flow_map = [
    "Administrator" => [
        "Create or update users",
        "Add or import assets",
        "Issue assignment",
        "Process transfer",
        "Record turnover",
        "Manage maintenance work orders",
        "Review clearance and history"
    ],
    "Asset Custodian" => [
        "Verify asset details",
        "Assign to employee",
        "Transfer between employees",
        "Receive returned asset",
        "Open and update work orders",
        "Update status and history",
        "Support clearance review"
    ],
    "HR / Clearance Officer" => [
        "Search employee",
        "Check accountable assets",
        "Request transfer or turnover",
        "Resolve accountability",
        "Print clearance form",
        "Archive final status"
    ],
    "Viewer" => [
        "Search asset records",
        "Open asset details",
        "Review assignment history",
        "Check status and accountability",
        "Request clarification",
        "No transaction rights"
    ]
];

$current_flow = $role_flow_map[$role] ?? $role_flow_map["Viewer"];

?>

<div class="page-header guide-page-header">
    <div>
        <p class="guide-page-label">HELP CENTER</p>
        <h2>Simple User Guide</h2>
        <p>Follow the steps below to use the system correctly.</p>
    </div>

    <div class="page-actions">
        <a
            href="/st/docs/manual/ST_Asset_Management_User_Manual.pdf"
            class="btn btn-secondary"
            target="_blank"
            rel="noopener noreferrer"
        >
            Open Full Manual
        </a>
        <a
            href="/st/docs/manual/ST_Asset_Management_Presentation_Workflow_Guide.pdf"
            class="btn btn-secondary"
            target="_blank"
            rel="noopener noreferrer"
        >
            Presentation Guide
        </a>
        <a
            href="/st/docs/manual/ST_Asset_Management_Maintenance_Addendum.md"
            class="btn btn-secondary"
        >
            Maintenance Addendum
        </a>
    </div>
</div>

<section class="guide-welcome" aria-labelledby="guide-welcome-title">
    <div class="guide-welcome-icon" aria-hidden="true">?</div>
    <div>
        <p class="guide-eyebrow">YOUR ACCESS LEVEL</p>
        <h2 id="guide-welcome-title"><?= htmlspecialchars($guide["title"]) ?></h2>
        <p><?= htmlspecialchars($guide["intro"]) ?></p>
    </div>
    <div class="guide-role-pill"><?= htmlspecialchars($role) ?></div>
</section>

<section class="guide-section" aria-labelledby="start-title">
    <div class="section-header">
        <div>
            <h2 id="start-title">Start here</h2>
            <p>Complete the steps in order when you are not sure what to do.</p>
        </div>
    </div>

    <div class="guide-simple-grid">
        <?php foreach ($guide["steps"] as $index => $step): ?>
            <article class="guide-simple-card">
                <div class="guide-step-number"><?= $index + 1 ?></div>
                <div class="guide-simple-content">
                    <h3><?= htmlspecialchars($step["title"]) ?></h3>
                    <p><?= htmlspecialchars($step["text"]) ?></p>
                    <a href="<?= htmlspecialchars($step["href"]) ?>" class="btn btn-secondary guide-simple-button">
                        <?= htmlspecialchars($step["button"]) ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="guide-section" aria-labelledby="flowchart-title">
    <div class="section-header">
        <div>
            <h2 id="flowchart-title">Asset process at a glance</h2>
            <p>Use this quick workflow to understand the standard asset life cycle.</p>
        </div>
    </div>

    <div class="guide-process-strip" aria-label="Asset process summary">
        <div class="guide-process-step">Record asset</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Verify details</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Assign to employee</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Transfer or turnover</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Clear accountability</div>
    </div>
</section>

<section class="guide-section" aria-labelledby="lifecycle-title">
    <div class="section-header">
        <div>
            <h2 id="lifecycle-title">Complete asset lifecycle</h2>
            <p>This is the recommended workflow to explain during a system presentation or live demonstration.</p>
        </div>
    </div>

    <div class="guide-process-strip" aria-label="Complete asset lifecycle">
        <div class="guide-process-step">Login</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Dashboard</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Register Asset</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Available</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Assign</div>
        <div class="guide-process-arrow" aria-hidden="true">→</div>
        <div class="guide-process-step">Assigned</div>
    </div>

    <div class="guide-rules-grid">
        <div class="guide-rule-card">
            <span class="guide-rule-icon">1</span>
            <div><h3>Assignment</h3><p>Available → Assigned. The current employee is recorded and an ASSIGNED history entry is created.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">2</span>
            <div><h3>Transfer</h3><p>Assigned employee A → employee B. The asset remains Assigned while the transaction history preserves the previous accountability.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">3</span>
            <div><h3>Turnover</h3><p>Assigned → Returned → Available. The current employee is cleared and a RETURNED history entry is recorded.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">4</span>
            <div><h3>Maintenance</h3><p>Available → Maintenance → Open/In Progress/Waiting for Parts → Completed. The asset becomes Available again when service is completed.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">5</span>
            <div><h3>QR verification</h3><p>Scan the asset QR code to connect the physical asset to its digital record and verify its information.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">6</span>
            <div><h3>History & reports</h3><p>Use History, printable forms, clearance, and Excel export for accountability, auditing, and management reporting.</p></div>
        </div>
    </div>

    <div class="guide-help-box">
        <h3>Presentation story</h3>
        <p>The system follows an asset from registration through accountability and maintenance while preserving the transactions that happened along the way. This means the current status shows where the asset is now, while History explains how it got there.</p>
    </div>
</section>

<section class="guide-section" aria-labelledby="role-summary-title">
    <div class="section-header">
        <div>
            <h2 id="role-summary-title">Quick role summary</h2>
            <p>Use this table to quickly match a user role with the actions they usually perform.</p>
        </div>
    </div>

    <div class="guide-role-summary">
        <?php foreach ($role_guides as $role_name => $role_info): ?>
            <div class="guide-role-summary-card <?= $role_name === $role ? 'active' : '' ?>">
                <div class="guide-role-header">
                    <span class="guide-role-badge"><?= htmlspecialchars($role_name) ?></span>
                </div>
                <ul>
                    <?php foreach ($role_info["steps"] as $step): ?>
                        <li><?= htmlspecialchars($step["title"]) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="guide-section" aria-labelledby="transaction-title">
    <div class="section-header">
        <div>
            <h2 id="transaction-title">Remember these rules</h2>
            <p>These simple rules help keep the asset records accurate.</p>
        </div>
    </div>

    <div class="guide-rules-grid">
        <div class="guide-rule-card">
            <span class="guide-rule-icon">1</span>
            <div>
                <h3>Assign</h3>
                <p>Use Assignment when an available asset is given to an employee.</p>
            </div>
        </div>

        <div class="guide-rule-card">
            <span class="guide-rule-icon">2</span>
            <div>
                <h3>Transfer</h3>
                <p>Use Transfer when an assigned asset moves from one employee to another.</p>
            </div>
        </div>

        <div class="guide-rule-card">
            <span class="guide-rule-icon">3</span>
            <div>
                <h3>Turnover</h3>
                <p>Use Turnover when an employee returns an assigned asset.</p>
            </div>
        </div>

        <div class="guide-rule-card">
            <span class="guide-rule-icon">4</span>
            <div>
                <h3>History</h3>
                <p>Check History to review previous asset transactions. Records are not erased.</p>
            </div>
        </div>

        <div class="guide-rule-card">
            <span class="guide-rule-icon">5</span>
            <div>
                <h3>Legacy / Existing</h3>
                <p>Old assets stay in the same Assets table. Mark their source as Legacy / Existing instead of creating a separate asset record type.</p>
            </div>
        </div>
    </div>
</section>


<section class="guide-section" aria-labelledby="field-reference-title">
    <div class="section-header">
        <div>
            <h2 id="field-reference-title">Transaction Field & Indicator Reference</h2>
            <p>Use this section whenever you are unsure what a field, status, button, or warning means.</p>
        </div>
    </div>

    <div class="guide-help-box">
        <h3>Before clicking Save / Submit</h3>
        <p>Confirm the asset, employee, condition, date, and remarks. A transaction changes accountability or asset status and is recorded in history. Do not create a second transaction to correct a mistake; check History and use the proper follow-up transaction.</p>
    </div>

    <div class="guide-rules-grid">
        <div class="guide-rule-card"><span class="guide-rule-icon">A</span><div><h3>Asset</h3><p>Select the physical asset involved. Always verify the asset code, name, type, and serial/plate number before submitting.</p></div></div>
        <div class="guide-rule-card"><span class="guide-rule-icon">B</span><div><h3>Employee / Accountability</h3><p>The selected employee becomes the person currently accountable for the asset. Active employees only appear in transaction selectors.</p></div></div>
        <div class="guide-rule-card"><span class="guide-rule-icon">C</span><div><h3>Condition</h3><p>Good = normal working condition; Fair = usable with minor wear/issues; Needs Inspection = requires checking or follow-up.</p></div></div>
        <div class="guide-rule-card"><span class="guide-rule-icon">D</span><div><h3>Remarks</h3><p>Use for transaction-specific facts: accessories, defects, reason for transfer, missing items, special instructions, or observations.</p></div></div>
        <div class="guide-rule-card"><span class="guide-rule-icon">E</span><div><h3>Required (*)</h3><p>A red asterisk means the field must be completed before the transaction can be submitted.</p></div></div>
        <div class="guide-rule-card"><span class="guide-rule-icon">F</span><div><h3>Searchable dropdown</h3><p>Type an asset code/name or employee name/ID/department to narrow the list, then select the correct record.</p></div></div>
    </div>

    <div class="guide-help-box">
        <h3>Asset status meanings</h3>
        <p><strong>Available:</strong> ready for assignment or eligible maintenance. <strong>Assigned:</strong> currently accountable to an employee. <strong>Maintenance:</strong> under an active work order and unavailable for normal assignment. <strong>Retired:</strong> permanently removed from normal operational use.</p>
    </div>

    <div class="guide-help-box">
        <h3>Maintenance status meanings</h3>
        <p><strong>Open:</strong> work has been created but not started. <strong>In Progress:</strong> service is actively being performed. <strong>Waiting for Parts:</strong> work is paused because parts/materials are needed. <strong>Completed:</strong> service is finished and the asset normally returns to Available. <strong>Cancelled:</strong> service will not proceed and the asset returns to its eligible prior state.</p>
    </div>

    <div class="guide-help-box">
        <h3>When to use each transaction</h3>
        <p><strong>Assignment:</strong> Available → employee. <strong>Transfer:</strong> employee A → employee B while the asset remains Assigned. <strong>Turnover:</strong> employee → organization/stock, clearing the current employee and returning the asset to Available. <strong>Maintenance:</strong> service/repair an unassigned asset. <strong>Clearance:</strong> verify that an employee has no currently assigned assets before printing clearance.</p>
    </div>

    <div class="guide-help-box">
        <h3>Important warnings</h3>
        <p>Do not manually force Assigned status during normal asset editing. Use Assignment/Transfer/Turnover. An Assigned asset must be turned over before maintenance. An asset with an active maintenance work order cannot be assigned or changed through normal bulk import. Retired assets cannot start new maintenance work.</p>
    </div>

    <div class="guide-help-box">
        <h3>What each indication tells you</h3>
        <p><strong>Success message:</strong> the transaction was accepted. <strong>Error message:</strong> the requested operation was rejected; read the message before retrying. <strong>Warning/attention indicator:</strong> review the item before continuing. <strong>Disabled button/field:</strong> the current role or asset state does not allow that action. <strong>History entry:</strong> the system recorded the transaction for auditability.</p>
    </div>
</section>

<section class="guide-section" aria-labelledby="excel-title">
    <div class="section-header">
        <div>
            <h2 id="excel-title">Excel Import / Export</h2>
            <p>Use one workbook to add or update assets, users, and linked items without deleting records that are not in the file.</p>
        </div>
    </div>

    <div class="guide-rules-grid">
        <div class="guide-rule-card">
            <span class="guide-rule-icon">1</span>
            <div><h3>Required asset fields</h3><p>Pink/red cells identify Asset Code, Asset Name, Category, and Asset Type as required.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">2</span>
            <div><h3>Update by Asset Code</h3><p>An existing Asset Code updates the existing record. A new Asset Code creates a new asset.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">3</span>
            <div><h3>Employee assignment</h3><p>Current Employee ID or Current Employee Name can identify the assignee; both are not required.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">4</span>
            <div><h3>Users Reference</h3><p>Employee information and Role can be updated in Excel. Passwords are never imported.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">5</span>
            <div><h3>Linked Items</h3><p>Use Linked Items for vehicle loose tools and other accessories/components attached to an asset.</p></div>
        </div>
    </div>
</section>

<section class="guide-section" aria-labelledby="status-title">
    <div class="section-header">
        <div>
            <h2 id="status-title">Asset status guide</h2>
            <p>What each status means.</p>
        </div>
    </div>

    <div class="guide-status-list">
        <div><span class="status-badge status-available">Available</span><p>The asset can be assigned.</p></div>
        <div><span class="status-badge status-assigned">Assigned</span><p>The asset is currently accountable to an employee.</p></div>
        <div><span class="status-badge status-maintenance">Maintenance</span><p>The asset is being repaired or checked.</p></div>
        <div><span class="status-badge status-retired">Retired</span><p>The asset is no longer in active use.</p></div>
    </div>
</section>

<section class="guide-section" aria-labelledby="maintenance-title">
    <div class="section-header">
        <div>
            <h2 id="maintenance-title">Maintenance work orders</h2>
            <p>Record service activity against the asset, not just its current status.</p>
        </div>
    </div>

    <div class="guide-rules-grid">
        <div class="guide-rule-card">
            <span class="guide-rule-icon">1</span>
            <div><h3>Open an order</h3><p>Select an unassigned Available or Maintenance asset. Set the service type, priority, target date, vendor or technician, and describe the issue. Add a PDF or image attachment if needed.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">2</span>
            <div><h3>Record the work</h3><p>Update the status as work progresses. Add diagnosis, work performed, labor cost, parts cost, and any supporting attachment. Changes appear in the work order history.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">3</span>
            <div><h3>Close the order</h3><p>Complete the order when service is finished or cancel it if service will not proceed. The asset returns to its eligible status; an asset retired during service stays Retired.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">4</span>
            <div><h3>Keep accountability clean</h3><p>Assigned assets must be returned through Turnover before maintenance. While an order is active, asset status is locked in Asset Edit, and assignments or Excel imports cannot change its status or accountability.</p></div>
        </div>
    </div>

    <?php if (in_array($role, ["Administrator", "Asset Custodian"], true)): ?>
        <div class="page-actions">
            <a href="/st/frontend/pages/maintenance.php" class="btn btn-primary">Open Maintenance</a>
        </div>
    <?php endif; ?>
</section>

<section class="guide-section" aria-labelledby="searchable-picker-title">
    <div class="section-header">
        <div>
            <h2 id="searchable-picker-title">Searching long dropdowns</h2>
            <p>Search and choose from one combined dropdown control.</p>
        </div>
    </div>
    <div class="guide-rules-grid">
        <div class="guide-rule-card">
            <span class="guide-rule-icon">1</span>
            <div><h3>Search as you type</h3><p>Type an asset code or name, employee name or ID, department, or category name to narrow the matching options inside the dropdown.</p></div>
        </div>
        <div class="guide-rule-card">
            <span class="guide-rule-icon">2</span>
            <div><h3>Open the option list</h3><p>Tap the arrow at the right of the same field to open the filtered options. Choose an option from the list; the form keeps the selected value.</p></div>
        </div>
    </div>
</section>

<section class="guide-help-box" aria-label="Need help">
    <h3>Need help?</h3>
    <p>If you cannot find a button or cannot complete an action, contact your Administrator or Asset Custodian. Do not create a duplicate asset record.</p>
</section>



<div class="detail-card">
    <div class="detail-card-header"><h3>Application Branding and Profile Picture</h3></div>
    <div class="detail-card-body">
        <p><strong>Administrator:</strong> Open Settings and use Application Branding to rename the application or upload/remove its logo.</p>
        <p><strong>All users:</strong> Open My Profile to upload or remove your profile picture/avatar.</p>
        <p class="secondary-text">Supported image formats are JPG, JPEG, PNG, and WEBP. Maximum file size is 2 MB.</p>
    </div>
</div>

<?php require_once "../../backend/includes/footer.php"; ?>
