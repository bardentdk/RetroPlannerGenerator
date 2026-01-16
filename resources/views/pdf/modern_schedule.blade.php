<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Planning de Formation</title>
    <style>
        @page { margin: 0cm; }
        body {
            font-family: 'Helvetica', sans-serif;
            background-color: #f8fafc;
            margin: 1cm;
            color: #1e293b;
        }

        /* HEADER */
        .header {
            margin-bottom: 25px;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            border-left: 6px solid #4f46e5; /* Indigo */
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .title { font-size: 20px; font-weight: 900; color: #1e293b; text-transform: uppercase; }
        .subtitle { font-size: 12px; color: #64748b; margin-top: 4px; }
        
        .header-infos { width: 100%; margin-top: 15px; border-top: 1px solid #f1f5f9; padding-top: 10px; }
        .info-label { font-size: 9px; text-transform: uppercase; color: #94a3b8; font-weight: bold; }
        .info-value { font-size: 14px; font-weight: bold; color: #334155; }
        .total-box { text-align: right; }
        .total-val { font-size: 22px; font-weight: 900; color: #4f46e5; }

        /* DAY CARD CONTAINER */
        .day-card {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 15px;
            page-break-inside: avoid;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .layout-table { width: 100%; border-collapse: collapse; }
        
        /* COLONNE DATE (GAUCHE) */
        .col-date {
            width: 80px;
            background-color: #f8fafc;
            text-align: center;
            vertical-align: middle;
            border-right: 1px solid #e2e8f0;
        }
        .day-num { font-size: 24px; font-weight: 800; color: #334155; line-height: 1; display: block; }
        .day-name { font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: bold; margin-top: 4px; display: block;}

        /* COLONNE CONTENU (CENTRE) */
        .col-content { padding: 0; vertical-align: top; }

        .session-row {
            padding: 12px 15px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .session-row:last-child { border-bottom: none; }

        .time-badge {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            padding: 2px 6px; border-radius: 4px; margin-right: 8px;
            display: inline-block; width: 55px; text-align: center;
        }
        .bg-matin { background: #fffbeb; color: #b45309; }
        .bg-aprem { background: #eff6ff; color: #1d4ed8; }

        .module-text { font-size: 12px; font-weight: bold; color: #0f172a; }
        .instructor-text { font-size: 10px; color: #64748b; font-style: italic; display: block; margin-top: 3px; margin-left: 75px; }

        /* COLONNE STATUT (DROITE) */
        .col-status {
            width: 90px;
            vertical-align: middle;
            text-align: center;
            border-left: 1px solid #e2e8f0;
            background-color: #fafafa;
            padding: 0 5px;
        }

        .status-pill {
            display: inline-block;
            padding: 3px 0;
            width: 100%;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 4px 0;
            text-align: center;
        }
        .pill-present { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .pill-absent { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .footer { position: fixed; bottom: 0.5cm; width: 100%; text-align: center; font-size: 9px; color: #cbd5e1; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">Planning de Formation</div>
        <div class="subtitle">{{ $trainingName }}</div>
        
        <table class="header-infos">
            <tr>
                <td>
                    <div class="info-label">Apprenant</div>
                    <div class="info-value">{{ $studentName }}</div>
                </td>
                <td class="total-box">
                    <div class="total-val">{{ $totalHeures }}h</div>
                    <div class="info-label">Volume Horaire Prévu</div>
                </td>
            </tr>
        </table>
    </div>

    @foreach($schedule as $date => $periods)
        @php
            $carbonDate = \Carbon\Carbon::parse($date);
        @endphp

        <div class="day-card">
            <table class="layout-table">
                <tr>
                    <td class="col-date">
                        <span class="day-num">{{ $carbonDate->format('d') }}</span>
                        <span class="day-name">{{ $carbonDate->locale('fr')->isoFormat('MMM') }}</span>
                        <div style="font-size:8px; color:#cbd5e1; margin-top:2px;">{{ $carbonDate->format('Y') }}</div>
                    </td>

                    <td class="col-content">
                        
                        <div class="session-row">
                            <span class="time-badge bg-matin">Matin</span>
                            @if($periods['morning'])
                                <span class="module-text">{{ $periods['morning']->module_name ?? 'Formation' }}</span>
                                <span class="instructor-text">Formateur : {{ $periods['morning']->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1; font-style:italic;">- Aucun cours prévu -</span>
                            @endif
                        </div>

                        <div class="session-row">
                            <span class="time-badge bg-aprem">Ap-Midi</span>
                            @if($periods['afternoon'])
                                <span class="module-text">{{ $periods['afternoon']->module_name ?? 'Formation' }}</span>
                                <span class="instructor-text">Formateur : {{ $periods['afternoon']->instructor_name ?? 'Non précisé' }}</span>
                            @else
                                <span class="module-text" style="color:#cbd5e1; font-style:italic;">- Aucun cours prévu -</span>
                            @endif
                        </div>
                    </td>

                    <td class="col-status">
                        @if($periods['morning'])
                            @if($periods['morning']->is_present)
                                <div class="status-pill pill-present">Présent</div>
                            @else
                                <div class="status-pill pill-absent">Absent</div>
                            @endif
                        @else
                            <div style="font-size:8px; color:#cbd5e1;">-</div>
                        @endif

                        <div style="height:1px; background:#e2e8f0; margin: 2px 10px;"></div>

                        @if($periods['afternoon'])
                            @if($periods['afternoon']->is_present)
                                <div class="status-pill pill-present">Présent</div>
                            @else
                                <div class="status-pill pill-absent">Absent</div>
                            @endif
                        @else
                            <div style="font-size:8px; color:#cbd5e1;">-</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <div class="footer">
        Document généré le {{ date('d/m/Y') }}
    </div>

</body>
</html>