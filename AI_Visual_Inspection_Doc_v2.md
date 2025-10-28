Prepared for **PT. Internasional Elektronik PCI**  
By **PT. Riyo Utama Indonesia** (Authorized Integration Partner)

Confidential – For Internal Review Only

---

## Table of Contents

1. Executive Overview
2. POC Validation & Stakeholder Demonstration
3. System Comparison – Current vs INDUSIA Solution
4. Scope of Work & Commercial Phases
5. System Architecture & Key Components
   - A. Mechanical Automation Subsystem (EVS Jig Integration)
   - B. Edge AI Computing Subsystem ⭐ **REVISED**
   - C. System-Level Integration
6. Hardware Workflow & False Call Management
7. Comprehensive Inspection Capability
8. Hardware Configuration
9. Service & Support Model
10. Training & Knowledge Transfer ⭐ **REVISED - 2 PHASES**

**APPENDICES**
- A. Training Workstation Specifications (Condensed)
- B. Mechanical Drawings & Renderings
- C. Licensing & Multi-Line Deployment Model

---

## 1. Executive Overview

The **INDUSIA AI Visual Inspection System** integrates AI-driven vision, mechanical automation, and Edge AI computing to deliver dual-side PCB inspection with unmatched precision. Following IPC-A-610 standards, it enhances accuracy, reduces false calls, and minimizes operator fatigue.

> *"Our mission is to let PCI engineers focus on improving production, while the AI continuously learns and adapts to every new model on the line."*

### Key Differentiators:

- ⭐ **Compact Jetson Orin Nano Edge AI System** (10×10 cm, 67 TOPS)
- Fanless industrial-grade design for 24/7 operation
- Customer-owned data and retraining capability
- Pre-trained PCB dataset library (>10,000 reference images)
- **Energy efficient: 7–15W power draw**

---

## 2. POC Validation & Stakeholder Demonstration

On **22 September 2025**, a live Proof of Concept (POC) was conducted at PT Internasional Elektronik PCI's EVS line, witnessed by Engineering teams. The objective was to validate real-time defect detection performance under actual operating conditions.

### Result:

- ✅ Achieved **>95% detection accuracy** (PASS/FAIL classification)
- ✅ Average inference latency **<100 ms**
- ✅ Seamless integration with EVS workstation and PLC synchronization

### POC Demo Media:

**Video Link:**  
https://drive.google.com/file/d/1bEz4N9niAZhcP8Ef_s8oOqnhJmQKBnox/view?usp=sharing

> *The successful POC confirmed both detection accuracy and mechanical reliability, demonstrating readiness for production-scale deployment.*

---

## 3. System Comparison – Current vs INDUSIA Solution

Unlike traditional AOI systems such as **Keyence XG-X2900**, which depend on static rule-based logic, the **INDUSIA AI System** leverages deep learning (CNN) to continuously improve using PCI's production data. This allows immediate retraining, data ownership, and vendor-independent scalability.

| Aspect | Keyence XG-X2900 (64 MP) | ✅ INDUSIA AI Visual Inspection System |
|--------|--------------------------|----------------------------------------|
| **Technology** | Rule-based logic | ✅ AI Deep Learning Vision (CNN) |
| **Inspection Scope** | Single-side only | ✅ Dual-side automated |
| **Camera Setup** | Multi-camera array | ✅ Single 12 MP camera with X-axis motion |
| **Lighting** | White only | ✅ White + UV illumination |
| **Retraining** | Vendor-locked | ✅ Customer retraining via VisualEditor |
| **Data Ownership** | Vendor-controlled | ✅ 100% Customer-owned |
| **Automation** | No PLC sync | ✅ Full PLC sync (<0.2 mm precision) |
| **Warranty / Support** | Ticket-based | ✅ SLA with 6-month warranty |
| **Total Investment (SGD)** | 28–30K (software only) | ✅ **24.5K** (AI + Automation + Mini PC + VisualEditor) |

> *INDUSIA AI empowers PCI with full control of data, retraining capability, and long-term scalability — transforming AOI from a static tool into a living, evolving platform.*

---

## 4. Scope of Work & Commercial Phases

Implementation is divided into **three structured phases**, each ensuring controlled deployment and measurable outcomes.

| Phase | Scope Description | Duration | Investment (SGD) |
|-------|------------------|----------|------------------|
| **Phase 1** | AI Inspection (2–3 models) + EVS Jig automation | Nov–Jan 2026 | **19,500** |
| **Phase 2** | VisualEditor deployment + training handover | Feb–Mei 2026 | **5,000** |
| | **Total Pilot Investment** | | **24,500** |

> *Each phase is executed under separate PO with independent acceptance and payment terms, ensuring clear delivery accountability.*

---

## 5. System Architecture & Key Components

The **INDUSIA AI System** is a seamless integration of AI computing, precision mechanical automation, and industrial imaging, implemented by **PT. Riyo Utama Indonesia** to align with PCI's EVS workstation standards.

---

### A. Mechanical Automation Subsystem (EVS Jig Integration)

**Key Features:**

- Dual-side flip & slide mechanism for top/bottom inspection
- Linear X-axis camera travel for connectors and high-profile components
- White + UV illumination for solder joint clarity
- Pneumatic control synchronized with EVS PLC (<0.2 mm repeatability)

**Demo Video:**  
https://drive.google.com/file/d/17osmLKTBNgW6ewUoytSczympvFynxYKA/view

**Visual References:**  
Refer to **Appendix B – Mechanical Drawings & Renderings** for detailed illustrations.

> *This subsystem automates imaging and eliminates manual handling errors, providing consistent lighting and focus.*

---

### B. Edge AI Computing Subsystem ⭐ **REVISED**

**Updated Hardware Specification:**

| Component | Specification | Notes |
|-----------|--------------|-------|
| **AI Processor** | **NVIDIA Jetson Orin Nano** | 67 TOPS AI performance |
| **Memory** | **32GB RAM** | Unified memory architecture |
| **Inference Latency** | **12–25 ms per frame** | Target performance (validated in POC) |
| **Form Factor** | **Fanless DIN-rail industrial design** | 10×10 cm compact module |
| **Storage** | **512 GB NVMe** | Auto-logging with 30-day retention |
| **Power Draw** | **7–15 W** | 50% more efficient than AGX variant |
| **Operating Temp** | **-25°C to 80°C** | Industrial-grade reliability |

**Architecture Benefits:**

✅ **Real-time inference** with full data ownership  
✅ **Zero cloud dependency** (all processing on-premises)  
✅ **Energy efficient** (7–15W)  
✅ **Industrial-grade fanless cooling** (24/7 operation)  
✅ **Compact integration** with EVS production line

**Cost Advantage:**

By selecting the Jetson Orin Nano variant, PT Riyo Utama Indonesia absorbs the hardware optimization cost while maintaining production-grade performance, delivering better value to PCI without compromising inspection quality.

> *Real-time inference, full data ownership, and zero cloud dependency — with 50% lower power consumption.*

---

### C. System-Level Integration

**Network Architecture:**

```
Training Workstation (Central)
    ↓ LAN (Gigabit Ethernet)
Jetson Orin Nano Edge PC (Production Line)
    ↓ Digital I/O + Ethernet/IP
EVS PLC Controller
    ↓ Pneumatic Control
Automation Jig (Flip + Slide + X-axis)
    ↓ Trigger Signal
Industrial Camera (12MP) + White/UV Lighting
```

**Data Flow:**

1. **Model Training** → Central workstation (GPU accelerated)
2. **Model Deployment** → Transfer to Jetson Edge via LAN
3. **Real-time Inspection** → Jetson processes images (<25ms inference)
4. **Result Logging** → PostgreSQL database + image archive
5. **Operator Feedback** → HMI dashboard (PASS/FAIL + override)
6. **Retraining Loop** → False positives sent back to workstation

---

## 6. Hardware Workflow & False Call Management

The INDUSIA AI Visual Inspection System operates under a **closed feedback loop** connecting the training workstation, the edge AI computer, and the operator interface. This design ensures that every inspection cycle contributes to continuous learning and accuracy improvement.

### Workflow Diagram

```
┌─────────────────────────┐         ┌──────────────────────┐
│  Central Training       │  LAN    │  Mini Inspection PC  │
│  & Annotation PC        │◄────────┤  (Production Line)   │
│                         │         │  Jetson Orin Nano    │
│  • Model Training       │         │  • Real-time AI      │
│  • Dataset Labeling     │         │  • PLC Sync          │
│  • Remote Access        │         │  • Auto Logging      │
└─────────────────────────┘         └──────────────────────┘
         ▲                                    │
         │                                    ▼
         │                          ┌──────────────────┐
         └──────────────────────────┤  Operator HMI    │
            False Call Feedback     │  • Review NG     │
            Retraining Request      │  • Override      │
                                    │  • Statistics    │
                                    └──────────────────┘
```

### Workflow Explanation:

1. **Dataset Training (Workstation)**: Engineers annotate PCB images and train AI models using the GPU workstation.

2. **Model Deployment**: Validated models are sent via LAN to the Jetson Edge PC installed on the EVS production line.

3. **Real-time Inspection**: The Jetson Edge performs AI inference in milliseconds, classifying each board as PASS or FAIL and logging all results locally.

4. **Operator Feedback**: Operators can review and manually override misclassified results (False Calls) directly through the HMI dashboard.

5. **Retraining Cycle**: False Call data and misclassified images are sent back to the workstation for retraining. Updated models are redeployed to production.

> *This continuous feedback mechanism keeps the AI models aligned with real production conditions, reducing false alarms and improving long-term reliability.*

---

## 7. Comprehensive Inspection Capability

The INDUSIA AI Visual Inspection System provides broad inspection coverage for **Manual Insert (MI)** and adjacent **Surface-Mount (SMT)** components on the same board. Using a 12-megapixel industrial camera and AI vision models, it identifies component, solder, and adhesive defects in mixed-technology assemblies.

### A. Component Inspection Scope

- **Manual Insert (MI)**: 100% polarity, solder, and label inspection; includes 10 mm edge zone coverage.
- **SMT Integrity (MI Zone)**: Verifies SMT parts near MI areas to ensure no post-handling damage or missing parts; maintains continuity between SMT and MI processes without duplicating SMT AOI scope.
- **Printed Labels & Markings**: Checks presence, alignment, and clarity of text or barcode labels.
- **Polarized Components**: Ensures correct orientation of diodes, electrolytic capacitors, LEDs, and IC pin-1 markers.

### B. Solder Joint Inspection (2D – 12 MP Camera)

Detects the **five major 2D solder defects**:

1. Solder Bridge / Short
2. Missing / Insufficient
3. Excess / Overflow
4. Solder Splash / Balling
5. Component Shift / Polarity Error

### C. Glue and Underfill Detection (UV Module)

- Detects presence, overflow, and smear of adhesives under UV illumination.
- Validates uniformity of underfill and potting applications.
- Enables combined solder + glue inspection in one cycle.

---

## 8. Hardware Configuration

| Component | Ownership | Phase | Remarks |
|-----------|-----------|-------|---------|
| Industrial Camera + Lens | PT Riyo Utama Indonesia | 1 | Supplied & integrated |
| Lighting (White + UV) | PT Riyo Utama Indonesia | 1 | Integrated to automation |
| Automation Machine (Flip + Slide) | PT Riyo Utama Indonesia | 1A | Fabricated & installed |
| **Mini Inspection PC** | **PT Riyo Utama Indonesia** | **1** | **Jetson Orin Nano Edge AI unit** ⭐ |
| Training Workstation (See Appendix A) | PT Internasional Elektronik PCI | Before Phase 3 | Central training + annotation node |

---

## 9. Training & Knowledge Transfer ⭐ **REVISED - 2 PHASES**

PT. Riyo Utama Indonesia provides a **phased training program** aligned with commercial deployment milestones, ensuring PCI achieves operational proficiency first, then full autonomy for AI model retraining.

---

### **Phase 1: Production Operations Training** (Week 23 – Factory Acceptance Testing)

**Timing:** During FAT and go-live preparation  
**Duration:** 2 days on-site  
**Audience:** Operators, Supervisors, Maintenance Technicians

**Training Modules:**

| Module | Focus Area | Duration | Participants |
|--------|-----------|----------|--------------|
| **1 – System Operation** | HMI dashboard, inspection workflow, PASS/FAIL interpretation | 4 hours | Operators, Supervisors |
| **2 – Model Management** | Load/switch models, monitor performance, confidence thresholds | 3 hours | Process Engineers, Supervisors |
| **3 – False Call Handling** | Override procedure, operator feedback, defect review queue | 3 hours | QA Staff, Operators |
| **5 – Maintenance & Troubleshooting** | Camera/lighting setup, PLC sync, spare parts, basic diagnostics | 6 hours | Maintenance Technicians, IT |

**Deliverables:**
- 📘 **INDUSIA AI Operations Handbook** (Bahasa Indonesia + English, PDF + printed)
- 🔧 **Maintenance Checklist** (laminated A3 poster for production floor)
- 🎥 **Video Tutorials**: 6× 5-10 minute clips (operator interface, troubleshooting)

**Objective:** Ensure production team can **operate, monitor, and maintain** the system independently for daily production without vendor dependency.

---

### **Phase 2: AI Model Training & Self-Service** (Month 7-8 – VisualEditor Deployment)

**Timing:** After VisualEditor tool deployment 
**Duration:** 2 days on-site + 6 months remote support  
**Audience:** QA Managers, Process Engineers, IT Administrators

**Training Modules:**

| Module | Focus Area | Duration | Participants |
|--------|-----------|----------|--------------|
| **4A – Dataset Annotation** | CVAT annotation interface, bounding box correction, quality standards | 4 hours | QA Engineers, Process Engineers |
| **4B – VisualEditor Model Training** | Training workflow, hyperparameter tuning, MLflow monitoring, A/B testing | 6 hours | AI/Process Engineers, QA Managers |
| **4C – Advanced Topics** | Transfer learning, data augmentation, troubleshooting low accuracy | 2 hours | Technical staff (optional) |

**Deliverables:**
- 📘 **VisualEditor User Manual** (Bahasa Indonesia + English, 25-30 pages)
- 🎥 **Video Tutorials**: 4× 10-15 minute clips (annotation, training, deployment)
- 📋 **Retraining Checklist**: Step-by-step procedure for new PCB models
- 🛠️ **6-Month Support Package**: Email/remote support (2-day response SLA)

**Objective:** Enable PCI to **train new PCB models** and **retrain existing models** independently, eliminating vendor dependency for model updates (cost savings: SGD 200-300 per retraining cycle).

---

### Training Investment Summary

| Phase | Timeline | Duration | Investment |
|-------|----------|----------|------------|
| **Phase 1** (Operations) | Week 23 (FAT) | 2 days | **Included in Phase 1 deployment** |
| **Phase 2** (AI Training) | Month 7-8 (VisualEditor) | 2 days | **Included in Phase 2 VisualEditor license** |

> *This phased approach ensures production readiness first (Phase 1), then empowers PCI with full AI autonomy (Phase 2) — transforming from system user to system owner.*

---

## APPENDICES

---

### A. Training Workstation Specifications (Condensed)

The Training Workstation is the central node for dataset preparation, annotation, model training, and validation before deployment to production lines.

| Specification | Standard (1–24 models) | Growth (25–60 models) | Enterprise (60–100+ models) |
|---------------|------------------------|----------------------|----------------------------|
| **CPU** | Ryzen 9 7900X / i7-13700K | Ryzen 9 7950X / i9-13900K | Threadripper PRO / Xeon W3 |
| **GPU** | RTX 4070 12GB | RTX 4070 Ti SUPER 16GB | 2× RTX 4090 24GB (NVLink opt.) |
| **RAM** | 32GB DDR5-5600 | 64GB DDR5-6000 | 128GB ECC DDR5 |
| **Storage** | 1TB NVMe Gen4 | 2TB NVMe Gen4 | 4TB NVMe RAID0 |
| **Archive** | 2TB SATA SSD | 4TB SATA SSD | 8TB NAS/SAN |
| **OS** | Ubuntu 22.04 / Windows 11 | Ubuntu 22.04 | Ubuntu 22.04 Server |
| **Training Time (24 models)** | 5–7 days | 3–4 days | 2–3 days |
| **Concurrent Jobs** | 1–2 | 3–4 | 6–8 |

**Performance Highlights:**

- Dataset capacity > 100,000 labeled images
- Real-time transfer learning and model compression (INT8/FP16)
- LAN synchronization to all Jetson units
- Auto backup and version control (daily schedule)
- Optional NAS integration for multi-line sharing

> *The workstation provides PCI with full control over model creation and deployment, enabling faster adaptation to new board types and defect patterns.*

---

### B. Mechanical Drawings & Renderings

**Figure B.1: Jig Automation Machine for EVS Model (Capture 1–5)**

*[3D renderings showing dual-side flip mechanism, X-axis camera travel, and pneumatic control integration]*

**Figure B.2: Drawing Jig Automation Machine (Engineering Schematic)**

*[Technical drawing showing dimensions: 800×610 mm footprint, component layout, and mounting specifications]*

**Figure B.3: PCB-MAGAZINE-LOADER**

The **PCB-MAGAZINE-LOADER** serves as the **automated board feeding system** in the INDUSIA AI Visual Inspection workflow. Its design enables **fully hands-free PCB handling**, ensuring consistent loading accuracy and continuous inspection flow.

#### Operational Concept:

1. The operator only needs to **place the PCB magazine** onto the loader station.
2. The system then **automatically pushes each PCB** sequentially onto the conveyor, delivering it to the EVS Jig Automation Machine for inspection by the INDUSIA AI Visual Inspection System.
3. Once inspection is complete:
   - **PASS units** are automatically unloaded to the downstream conveyor for the next process station.
   - **NG (defective) units** trigger a **visual and audible alarm**, allowing operators to retrieve the board for review or rework.

#### Delivery & Commercial Notes:

- The **PCB-MAGAZINE-LOADER** will be delivered during **Phase 2** of the project schedule.
- **Pricing details** will be formally presented upon completion of Phase 1, after system validation and performance acceptance.
- Integration of this automation is projected to deliver **a minimum 1 operator per shift** reduction, by eliminating manual board handling between inspection and subsequent stations.

> *This automation module not only improves throughput and consistency but also reduces operator workload, contributing directly to PCI's long-term efficiency gains.*

---

### C. Licensing & Multi-Line Deployment Model

| Line | Scope | Inclusions | Investment (SGD) |
|------|-------|-----------|------------------|
| **Line 1** | Pilot deployment | Camera + Jetson Edge + AI software + Automation | 22,000 |
| **Lines 2–5** | Additional lines | Camera + Jetson Edge + setup + license | 8,000 / line

**License Terms:**
- **Perpetual ownership**: One-time payment, no recurring annual fees
- **Hardware-locked**: Each license binds to 1 Mini PC (MAC + CPU + Motherboard)
- **Re-activation policy**: Hardware replacement/upgrade supported within 24 hours (no charge)
- **Scope limitation**: Valid only within PT Internasional Elektronik PCI facilities
- **Multi-line expansion**: Additional lines require separate licenses (SGD 8,000/line)

---

## Document Approval

| Prepared By | Reviewed By | Approved By |
|-------------|-------------|-------------|
| **PT. Riyo Utama Indonesia** | **PT. Internasional Elektronik PCI** | |
| Project Director: | Engineering Manager: | |
| ___________________ | ___________________ | Date: __________ |