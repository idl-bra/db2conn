<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DB2 Test Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .card h2 {
            color: #1e40af;
            font-size: 18px;
            margin-bottom: 12px;
        }
        .card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 12px;
        }
        a {
            display: inline-block;
            background: #1e40af;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 8px;
            transition: background 0.3s;
        }
        a:hover {
            background: #1e3a8a;
        }
        .query-form {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        input[type="text"] {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: monospace;
        }
        button {
            background: #1e40af;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        button:hover {
            background: #1e3a8a;
        }
        .result {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px;
            margin-top: 12px;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .status-ok {
            color: #16a34a;
        }
        .status-erro {
            color: #dc2626;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ DB2 Test Dashboard</h1>

        <div class="grid">
            <div class="card">
                <h2>✅ Testar Conexão</h2>
                <p>Valida a conexão com o servidor DB2 via PDO_ODBC.</p>
                <button onclick="testConexao()">Testar Conexão</button>
                <div id="result-conexao" class="result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>📋 Listar Tabelas</h2>
                <p>Lista todas as tabelas da schema/biblioteca atual.</p>
                <button onclick="testarTabelas()">Listar Tabelas</button>
                <div id="result-tabelas" class="result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>🔍 Verificar Tabela</h2>
                <p>Busca uma tabela específica e lista suas colunas.</p>
                <div class="query-form">
                    <input type="text" id="table-name" placeholder="Ex: USERS, PEDIDOS" />
                    <button onclick="testarTabela()">Buscar</button>
                </div>
                <div id="result-tabela" class="result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>💬 Query Customizada</h2>
                <p>Execute uma query SQL customizada (SELECT apenas).</p>
                <div class="query-form">
                    <input type="text" id="custom-sql" placeholder="SELECT * FROM USERS LIMIT 10" />
                    <button onclick="testarQuery()">Executar</button>
                </div>
                <div id="result-query" class="result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>🎯 Grammar (SQL Gerado)</h2>
                <p>Valida o SQL gerado pelo Query Builder (sem executar).</p>
                <button onclick="testarGrammar()">Gerar SQL</button>
                <div id="result-grammar" class="result" style="display:none;"></div>
            </div>

            <div class="card">
                <h2>📚 Documentação</h2>
                <p>Consulte o README.md do package para mais detalhes sobre configuração e uso.</p>
            </div>
        </div>
    </div>

    <script>
        function showResult(elementId, data) {
            const el = document.getElementById(elementId);
            el.textContent = JSON.stringify(data, null, 2);
            el.style.display = 'block';
        }

        function testConexao() {
            fetch('/db2-test/conexao')
                .then(r => r.json())
                .then(data => showResult('result-conexao', data));
        }

        function testarTabelas() {
            fetch('/db2-test/tabelas')
                .then(r => r.json())
                .then(data => showResult('result-tabelas', data));
        }

        function testarTabela() {
            const table = document.getElementById('table-name').value;
            if (!table) {
                alert('Preencha o nome da tabela');
                return;
            }
            fetch(`/db2-test/tabela/${table}`)
                .then(r => r.json())
                .then(data => showResult('result-tabela', data));
        }

        function testarQuery() {
            const sql = document.getElementById('custom-sql').value;
            if (!sql) {
                alert('Preencha a query');
                return;
            }
            fetch(`/db2-test/query?sql=${encodeURIComponent(sql)}`)
                .then(r => r.json())
                .then(data => showResult('result-query', data));
        }

        function testarGrammar() {
            fetch('/db2-test/grammar')
                .then(r => r.json())
                .then(data => showResult('result-grammar', data));
        }
    </script>
</body>
</html>
