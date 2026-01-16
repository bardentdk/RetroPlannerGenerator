<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        @page { margin: 0cm; }
        body {
            font-family: 'Helvetica', sans-serif;
            background-color: #ffffff;
            margin: 1.5cm;
            color: #1e293b;
        }

        /* HEADER */
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
        }
        .brand { font-size: 11px; text-transform: uppercase; color: #94a3b8; letter-spacing: 2px; font-weight: bold; }
        .title { font-size: 22px; font-weight: 900; color: #0f172a; margin-top: 5px; }
        .student-box { 
            background: #f8fafc; 
            border-left: 4px solid #4f46e5; 
            padding: 15px; 
            margin-top: 15px; 
            border-radius: 0 4px 4px 0;
        }
        .student-label { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: bold; }
        .student-name { font-size: 16px; font-weight: bold; color: #334155; }

        /* STATS */
        .stats-table { width: 100%; margin-top: 15px; border-collapse: collapse; }
        .stat-val { font-size: 24px; font-weight: 900; color: #4f46e5; }
        .stat-lbl { font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: bold; }

        /* PLANNING LIST */
        .planning-container { margin-top: 30px; }
        .day-row {
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            page-break-inside: avoid;
        }
        .day-row:last-child { border-bottom: none; }

        .date-col { display: inline-block; width: 18%; vertical-align: top; }
        .date-day { font-size: 14px; font-weight: bold; color: #334155; }
        .date-month { font-size: 10px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }

        .content-col { display: inline-block; width: 80%; vertical-align: top; }
        
        .slot { margin-bottom: 6px; position: relative; padding-left: 60px; min-height: 25px; }
        .slot-time { 
            position: absolute; left: 0; top: 2px;
            font-size: 9px; font-weight: bold; color: #94a3b8; 
            width: 50px; text-align: right;
        }
        .slot-info { border-left: 2px solid #e2e8f0; padding-left: 10px; margin-left: 5px; }
        .module { font-size: 11px; font-weight: bold; color: #1e293b; }
        .instructor { font-size: 10px; color: #64748b; font-style: italic; }

        /* STATUS DOTS (Discret) */
        .status-dot {
            display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 5px;
        }
        .dot-green { background-color: #22c55e; } /* Présent */
        .dot-red { background-color: #ef4444; }   /* Absent/Inconnu */
        
        .footer { position: fixed; bottom: 1cm; left: 0; right: 0; text-align: center; font-size: 9px; color: #cbd5e1; }
    </style>
</head>
<body>

    <div class="header">
        <div class="brand">Organisme de Formation</div>
        <div class="title">Planning Général de Formation</div>
        
        <table width="100%">
            <tr>
                <td width="60%">
                    <div class="student-box">
                        <div class="student-label">Apprenant</div>
                        <div class="student-name">{{ $studentName }}</div>
                    </div>
                </td>
                <td width="40%" align="right" style="vertical-align: bottom;">
                    <div class="stat-val">{{ $totalHeures }}h</div>
                    <div class="stat-lbl">Volume Horaire Prévu</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="planning-container">
        @foreach($schedule as $date => $periods)
            @php
                $carbonDate = \Carbon\Carbon::parse($date);
            @endphp
            <div class="day-row">
                <div class="date-col">
                    <div class="date-day">{{ $carbonDate->locale('fr')->isoFormat('ddd D') }}</div>
                    <div class="date-month">{{ $carbonDate->locale('fr')->isoFormat('MMM YYYY') }}</div>
                </div>

                <div class="content-col">
                    
                    @if($periods['morning'])
                        <div class="slot">
                            <div class="slot-time">08:30 - 12:00</div>
                            <div class="slot-info" style="border-left-color: {{ $periods['morning']->is_present ? '#22c55e' : '#cbd5e1' }}">
                                <div class="module">
                                    {{ $periods['morning']->module_name ?? 'Formation' }}
                                </div>
                                <div class="instructor">
                                    {{ $periods['morning']->instructor_name ?? 'Intervenant non précisé' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($periods['afternoon'])
                        <div class="slot">
                            <div class="slot-time">13:00 - 16:30</div>
                            <div class="slot-info" style="border-left-color: {{ $periods['afternoon']->is_present ? '#22c55e' : '#cbd5e1' }}">
                                <div class="module">
                                    {{ $periods['afternoon']->module_name ?? 'Formation' }}
                                </div>
                                <div class="instructor">
                                    {{ $periods['afternoon']->instructor_name ?? 'Intervenant non précisé' }}
                                </div>
                            </div>
                        </div>
                    @endif

                </div>
            </div>
        @endforeach
    </div>

    <div class="footer">
        Document généré automatiquement le {{ date('d/m/Y') }}
    </div>

</body>
</html>