<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        @page { margin: 0cm; }
        body {
            font-family: 'Helvetica', sans-serif;
            background-color: #f1f5f9; /* Fond gris très clair */
            margin: 1cm;
            color: #1e293b;
        }

        /* HEADER */
        .header {
            margin-bottom: 30px;
            border-bottom: 3px solid #4f46e5; /* Indigo */
            padding-bottom: 20px;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .title { font-size: 24px; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: 1px; }
        .subtitle { font-size: 14px; color: #64748b; margin-top: 5px; }
        
        .student-badge {
            background-color: #eef2ff;
            color: #4f46e5;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
            margin-top: 10px;
            border: 1px solid #c7d2fe;
        }

        /* CARD STYLE ROW */
        .card-row {
            background-color: #ffffff;
            border-radius: 10px;
            margin-bottom: 12px;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            page-break-inside: avoid; /* Évite de couper une carte en deux */
            border-left: 5px solid #cbd5e1; /* Bordure par défaut */
        }

        .card-table { width: 100%; border-collapse: collapse; }
        .card-table td { padding: 15px; vertical-align: middle; }

        /* COULEURS BORDURE GAUCHE SELON PÉRIODE */
        .border-morning { border-left-color: #f59e0b !important; } /* Orange Matin */
        .border-afternoon { border-left-color: #3b82f6 !important; } /* Bleu Aprèm */

        /* DATE BOX */
        .date-box {
            text-align: center;
            width: 80px;
            border-right: 1px solid #f1f5f9;
        }
        .day-num { font-size: 24px; font-weight: 800; color: #334155; line-height: 1; }
        .day-month { font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }

        /* CONTENT BOX */
        .content-box { padding-left: 20px !important; }
        .period-badge {
            font-size: 9px; 
            font-weight: bold; 
            text-transform: uppercase; 
            padding: 3px 8px; 
            border-radius: 4px;
            margin-bottom: 6px;
            display: inline-block;
        }
        .bg-morning { background: #fffbeb; color: #b45309; }
        .bg-afternoon { background: #eff6ff; color: #1d4ed8; }

        .module-title { font-size: 14px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .instructor { font-size: 11px; color: #64748b; font-style: italic; }
        .instructor span { font-weight: bold; color: #475569; }

        /* STATUS BOX */
        .status-box { text-align: right; width: 100px; }
        .status-pill {
            padding: 6px 12px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .present { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .absent { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .footer { text-align: center; color: #94a3b8; font-size: 10px; margin-top: 30px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">Programme de Formation</div>
        <div class="subtitle">Mon Passeport pour l'Insertion</div>
        <!-- <div class="subtitle">{{ $trainingName }}</div> -->
        <div class="student-badge">👤 {{ $studentName }}</div>
    </div>

    @foreach($days as $date => $slots)
        @foreach($slots as $slot)
            @php
                $carbonDate = \Carbon\Carbon::parse($slot->date);
                $isMorning = $slot->period === 'morning';
                $borderColor = $isMorning ? 'border-morning' : 'border-afternoon';
                $periodLabel = $isMorning ? '08:30 - 12:00' : '13:00 - 16:30';
                $periodClass = $isMorning ? 'bg-morning' : 'bg-afternoon';
            @endphp

            <div class="card-row {{ $borderColor }}">
                <table class="card-table">
                    <tr>
                        <td class="date-box">
                            <div class="day-num">{{ $carbonDate->format('d') }}</div>
                            <div class="day-month">{{ $carbonDate->locale('fr')->isoFormat('MMM') }}</div>
                            <div style="font-size:9px; color:#cbd5e1;">{{ $carbonDate->format('Y') }}</div>
                        </td>

                        <td class="content-box">
                            <span class="period-badge {{ $periodClass }}">{{ $periodLabel }}</span>
                            <div class="module-title">
                                {{ $slot->module_name ?? 'Module de formation' }}
                            </div>
                            <div class="instructor">
                                Intervenant : <span>{{ $slot->instructor_name ?? 'Non spécifié' }}</span>
                            </div>
                        </td>

                        <td class="status-box">
                            @if($slot->is_present)
                                <span class="status-pill present">Présent</span>
                            @else
                                <span class="status-pill absent">Absent</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endforeach

    <!-- <div class="footer">
        Document généré le {{ date('d/m/Y') }} • PlanningGen Solutions
    </div> -->

</body>
</html>