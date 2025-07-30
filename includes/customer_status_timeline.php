<?php
/**
 * Customer Status Timeline Component
 * Displays the history of status changes for a customer
 */

// This file should be included where needed, with $customer_id and $messages already available
if (!isset($customer_id) || !isset($messages)) {
    die('Required variables not set');
}

require_once __DIR__ . '/customer_status_functions.php';

// Get the customer's status timeline
$status_timeline = getCustomerStatusTimeline($customer_id, $current_locale ?? 'en');
?>

<div class="status-timeline-container">
    <h3><?= htmlspecialchars($messages['status_timeline']) ?></h3>
    
    <?php if (empty($status_timeline)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <?= htmlspecialchars($messages['no_status_history']) ?>
        </div>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($status_timeline as $change): ?>
                <div class="timeline-item">
                    <div class="timeline-marker">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-date">
                                <?= date('Y-m-d H:i', strtotime($change['changed_at'])) ?>
                            </span>
                            <span class="timeline-user">
                                <?= htmlspecialchars($messages['status_changed_by']) ?>: 
                                <?= htmlspecialchars($change['changed_by_username'] ?? 'System') ?>
                            </span>
                        </div>
                        <div class="timeline-body">
                            <?php if ($change['from_status_name']): ?>
                                <span class="status-change">
                                    <?= htmlspecialchars($messages['status_changed_from']) ?> 
                                    <span class="status-badge status-<?= htmlspecialchars($change['from_status_key']) ?>">
                                        <?= htmlspecialchars($change['from_status_name']) ?>
                                    </span>
                                    <?= htmlspecialchars($messages['status_changed_to']) ?> 
                                    <span class="status-badge status-<?= htmlspecialchars($change['to_status_key']) ?>">
                                        <?= htmlspecialchars($change['to_status_name']) ?>
                                    </span>
                                </span>
                            <?php else: ?>
                                <span class="status-change">
                                    <?= htmlspecialchars($messages['initial_status']) ?>: 
                                    <span class="status-badge status-<?= htmlspecialchars($change['to_status_key']) ?>">
                                        <?= htmlspecialchars($change['to_status_name']) ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($change['notes'])): ?>
                            <div class="timeline-notes">
                                <small class="text-muted">
                                    <?= htmlspecialchars($messages['status_change_notes']) ?>: 
                                    <?= htmlspecialchars($change['notes']) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.status-timeline-container {
    margin: 20px 0;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 10px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -25px;
    top: 5px;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
    border: 2px solid #007bff;
    border-radius: 50%;
    z-index: 1;
}

.timeline-marker i {
    font-size: 8px;
    color: #007bff;
}

.timeline-content {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-left: 10px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    flex-wrap: wrap;
    gap: 10px;
}

.timeline-date {
    font-weight: bold;
    color: #495057;
}

.timeline-user {
    font-size: 0.9em;
    color: #6c757d;
}

.timeline-body {
    margin-bottom: 10px;
}

.status-change {
    font-size: 1em;
    line-height: 1.5;
}

.status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.85em;
    font-weight: 500;
    margin: 0 2px;
}

/* Status-specific colors */
.status-prospect {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.status-qualified {
    background-color: #fff3e0;
    color: #f57c00;
    border: 1px solid #ffcc02;
}

.status-not_qualified {
    background-color: #ffebee;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.status-new_customer {
    background-color: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #c8e6c8;
}

.status-active_customer {
    background-color: #e0f2f1;
    color: #00695c;
    border: 1px solid #b2dfdb;
}

.status-lost_customer {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #e1bee7;
}

.timeline-notes {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #dee2e6;
}

/* Responsive design */
@media (max-width: 768px) {
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .status-change {
        font-size: 0.9em;
    }
    
    .status-badge {
        font-size: 0.8em;
        margin: 1px;
    }
}
</style>
