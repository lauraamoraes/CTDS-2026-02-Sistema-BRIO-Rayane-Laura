<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/response.php';
require_once __DIR__ . '/config/auth.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

function requirePlanoPago($pdo, $uid)
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM usuarios u
         INNER JOIN planos p
            ON p.id = u.plano_atual_id
         INNER JOIN assinaturas a
            ON a.usuario_id = u.id
            AND a.plano_id = p.id
         WHERE u.id = ?
         AND p.valor_mensal > 0
         AND a.status = 'ativa'
         AND a.fim >= CURDATE()"
    );

    $stmt->execute([$uid]);

    if ((int)$stmt->fetchColumn() === 0) {
        jsonResponse([
            'erro' => 'Essa funcionalidade está disponível apenas nos planos pagos.'
        ], 403);
    }
}

try {

    /*
     * A conexão fica DENTRO do try.
     * Assim, se o MySQL estiver com problema,
     * o servidor ainda consegue devolver JSON.
     */
    $pdo = Database::connect();


    switch ($action) {
        
case 'register_professor':

    if ($method !== 'POST') {
        jsonResponse(['erro' => 'Método inválido.'], 405);
    }

    // O cadastro do professor recebe multipart/form-data,
    // não JSON, porque inclui um arquivo.
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if (
        mb_strlen($nome) < 3 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($senha) < 8
    ) {
        jsonResponse([
            'erro' => 'Informe nome, e-mail válido e senha de pelo menos 8 caracteres.'
        ], 422);
    }

    if ($senha !== $confirmar) {
        jsonResponse(['erro' => 'As senhas não coincidem.'], 422);
    }

    if (
        !isset($_FILES['diploma']) ||
        $_FILES['diploma']['error'] !== UPLOAD_ERR_OK
    ) {
        jsonResponse(['erro' => 'Envie seu diploma.'], 422);
    }

    $arquivo = $_FILES['diploma'];

    if ($arquivo['size'] > 5 * 1024 * 1024) {
        jsonResponse(['erro' => 'O diploma deve ter no máximo 5 MB.'], 422);
    }

    // Confere o tipo real do arquivo.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    $tiposPermitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($tiposPermitidos[$mime])) {
        jsonResponse([
            'erro' => 'Formato inválido. Envie PDF, JPG ou PNG.'
        ], 422);
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = ?'
    );
    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        jsonResponse(['erro' => 'Este e-mail já está cadastrado.'], 409);
    }

    // Guarde os documentos fora da pasta pública frontend.
    $diretorio = dirname(__DIR__) . '/documentos_privados/professores/';

    if (!is_dir($diretorio) &&
        !mkdir($diretorio, 0700, true) &&
        !is_dir($diretorio)) {
        jsonResponse(['erro' => 'Não foi possível preparar o armazenamento.'], 500);
    }

    $nomeArquivo = bin2hex(random_bytes(16))
        . '.' . $tiposPermitidos[$mime];

    $caminho = $diretorio . $nomeArquivo;

    if (!move_uploaded_file($arquivo['tmp_name'], $caminho)) {
        jsonResponse(['erro' => 'Não foi possível salvar o diploma.'], 500);
    }

    try {
        $pdo->beginTransaction();

        // Mantém o hash SHA-256 usado pelo login atual.
        // Posteriormente, recomendamos migrar para password_hash().
        $hash = hash('sha256', $senha);

       // 1. Cadastra o professor na tabela de usuários
$stmt = $pdo->prepare("
    INSERT INTO usuarios
        (nome, email, senha, tipo, objetivo)
    VALUES
        (?, ?, ?, 'professor', ?)
");

$stmt->execute([
    $nome,
    $email,
    $hash,
    ''
]);

// 2. Recupera o ID do professor cadastrado
$usuarioId = (int)$pdo->lastInsertId();

// 3. Salva o diploma com aprovação automática
$stmt = $pdo->prepare("
    INSERT INTO documentos_professores
        (usuario_id, diploma_arquivo, status)
    VALUES
        (?, ?, 'aprovado')
");

$stmt->execute([
    $usuarioId,
    $nomeArquivo
]);

        
$pdo->commit();

// Inicia a sessão do professor automaticamente.
session_regenerate_id(true);

$_SESSION['usuario_id'] = $usuarioId;
$_SESSION['usuario_nome'] = $nome;
$_SESSION['usuario_tipo'] = 'professor';

jsonResponse([
    'ok' => true,
    'mensagem' => 'Cadastro realizado com sucesso!',
    'usuario' => [
        'id' => $usuarioId,
        'nome' => $nome,
        'tipo' => 'professor'
    ]
], 201);


    } catch (Throwable $erro) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (is_file($caminho)) {
            unlink($caminho);
        }

        jsonResponse([
            'erro' => 'Não foi possível concluir o cadastro.'
        ], 500);
    }

    break;

        case 'verificar_plano':

    $uid = requireLogin();

    $stmt = $pdo->prepare(
        "SELECT
            p.nome,
            p.valor_mensal,
            a.status,
            a.fim

         FROM usuarios u

         LEFT JOIN planos p
            ON p.id = u.plano_atual_id

         LEFT JOIN assinaturas a
            ON a.usuario_id = u.id
            AND a.plano_id = p.id
            AND a.status = 'ativa'
            AND a.fim >= CURDATE()

         WHERE u.id = ?"
    );

    $stmt->execute([$uid]);

    $plano = $stmt->fetch();

    $pago = (
        $plano &&
        $plano['status'] === 'ativa' &&
        (float)$plano['valor_mensal'] > 0
    );

    jsonResponse([
        'pago' => $pago,
        'plano' => $plano['nome'] ?? 'Gratuito'
    ]);

    break;

        /* =========================
           CADASTRO
        ========================= */
        
        case 'register':

            if ($method !== 'POST') {

                jsonResponse([
                    'erro' => 'Método inválido.'
                ], 405);
            }

            $d = body();

            $nome =
                trim($d['nome'] ?? '');

            $email =
                trim($d['email'] ?? '');

            $senha =
                $d['senha'] ?? '';

            $objetivo =
                trim($d['objetivo'] ?? '');


            if (
                strlen($nome) < 3 ||
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) ||
                strlen($senha) < 6
            ) {

                jsonResponse([
                    'erro' =>
                        'Preencha nome, e-mail válido e senha com pelo menos 6 caracteres.'
                ], 422);
            }


            $stmt =
                $pdo->prepare(
                    'SELECT id FROM usuarios WHERE email = ?'
                );

            $stmt->execute([
                $email
            ]);


            if ($stmt->fetch()) {

                jsonResponse([
                    'erro' =>
                        'Este e-mail já está cadastrado.'
                ], 409);
            }


            $hash =
                hash(
                    'sha256',
                    $senha
                );


            $stmt =
                $pdo->prepare(
                    'INSERT INTO usuarios
                    (nome, email, senha, objetivo)
                    VALUES (?, ?, ?, ?)'
                );


            $stmt->execute([
                $nome,
                $email,
                $hash,
                $objetivo
            ]);


            jsonResponse([
                'ok' => true,
                'mensagem' =>
                    'Cadastro realizado com sucesso.'
            ], 201);

            break;


        /* =========================
           LOGIN
        ========================= */

        case 'login':

            if ($method !== 'POST') {

                jsonResponse([
                    'erro' => 'Método inválido.'
                ], 405);
            }

            $d = body();

            $email =
                trim($d['email'] ?? '');

            $senha =
                $d['senha'] ?? '';


            $stmt =
                $pdo->prepare(
                    'SELECT * FROM usuarios
                     WHERE email = ?
                     LIMIT 1'
                );

            $stmt->execute([
                $email
            ]);

            $usuario =
                $stmt->fetch();


            if (
                !$usuario ||
                !hash_equals(
                    $usuario['senha'],
                    hash(
                        'sha256',
                        $senha
                    )
                )
            ) {

                jsonResponse([
                    'erro' =>
                        'E-mail ou senha incorretos.'
                ], 401);
            }
            




            $_SESSION['usuario_id'] =
                $usuario['id'];

            $_SESSION['usuario_nome'] =
                $usuario['nome'];

            $_SESSION['usuario_tipo'] =
                $usuario['tipo'];


            jsonResponse([
                'ok' => true,

                'usuario' => [
                    'id' =>
                        (int)$usuario['id'],

                    'nome' =>
                        $usuario['nome'],

                    'tipo' =>
                        $usuario['tipo']
                ]
            ]);

            break;


        /* =========================
           LOGOUT
        ========================= */

        case 'logout':

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    jsonResponse([
        'ok' => true,
        'mensagem' => 'Logout realizado com sucesso.'
    ]);

    break;


        /* =========================
           USUÁRIO LOGADO
        ========================= */

        case 'me':

            jsonResponse([
                'usuario' =>
                    currentUser()
            ]);

            break;


        /* =========================
           DASHBOARD
        ========================= */

        case 'dashboard':

            $uid =
                requireLogin();


            $stmt =
                $pdo->prepare(
                    'SELECT
                        COALESCE(
                            SUM(quantidade),
                            0
                        ) AS xp
                     FROM pontuacoes
                     WHERE usuario_id = ?'
                );

            $stmt->execute([
                $uid
            ]);

            $xp =
                (int)$stmt->fetch()['xp'];


            $nivel =
                max(
                    1,
                    (int)floor(
                        $xp / 250
                    ) + 1
                );


            $stmt =
                $pdo->prepare(
                    "SELECT COUNT(*) AS total
                     FROM tarefas
                     WHERE usuario_id = ?
                     AND status = 'concluida'"
                );

            $stmt->execute([
                $uid
            ]);

            $concluidas =
                (int)$stmt->fetch()['total'];


            $stmt =
                $pdo->prepare(
                    'SELECT COUNT(*) AS total
                     FROM redacoes
                     WHERE usuario_id = ?'
                );

            $stmt->execute([
                $uid
            ]);

            $redacoes =
                (int)$stmt->fetch()['total'];


            $stmt =
                $pdo->prepare(
                    'SELECT COUNT(*) AS total
                     FROM tentativas
                     WHERE usuario_id = ?'
                );

            $stmt->execute([
                $uid
            ]);

            $simulados =
                (int)$stmt->fetch()['total'];


            $stmt =
                $pdo->prepare(
                    'SELECT
                        id,
                        titulo,
                        data,
                        horario,
                        tipo,
                        status
                     FROM tarefas
                     WHERE usuario_id = ?
                     ORDER BY data, horario
                     LIMIT 6'
                );

            $stmt->execute([
                $uid
            ]);

            $tarefas =
                $stmt->fetchAll();


            jsonResponse([

                'xp' =>
                    $xp,

                'nivel' =>
                    $nivel,

                'concluidas' =>
                    $concluidas,

                'redacoes' =>
                    $redacoes,

                'simulados' =>
                    $simulados,

                'tarefas' =>
                    $tarefas
            ]);

            break;


        /* =========================
           MATÉRIAS
        ========================= */

        case 'materias':

            $resultado =
                $pdo->query(
                    'SELECT *
                     FROM materias
                     ORDER BY nome'
                );

            jsonResponse(
                $resultado->fetchAll()
            );

            break;


        /* =========================
           CONTEÚDOS
        ========================= */

        case 'conteudos':

            $uid =
                requireLogin();


            $sql = "
                SELECT
                    c.*,
                    m.nome AS materia,

                    COALESCE(
                        p.percentual,
                        0
                    ) AS percentual,

                    COALESCE(
                        p.concluido,
                        0
                    ) AS concluido

                FROM conteudos c

                JOIN materias m
                    ON m.id = c.materia_id

                LEFT JOIN progresso_conteudos p
                    ON p.conteudo_id = c.id
                    AND p.usuario_id = ?

                ORDER BY c.id DESC
            ";


            $stmt =
                $pdo->prepare($sql);

            $stmt->execute([
                $uid
            ]);


            jsonResponse(
                $stmt->fetchAll()
            );

            break;


        /* =========================
           PROGRESSO
        ========================= */

        case 'progresso':

            $uid = requireLogin();

            if ($method !== 'POST') {
                jsonResponse(['erro' => 'Método inválido.'], 405);
            }

            $d = body();
            $conteudo = (int)($d['conteudo_id'] ?? 0);
            $percentual = min(100, max(0, (int)($d['percentual'] ?? 0)));

            if ($conteudo <= 0) {
                jsonResponse(['erro' => 'Conteúdo inválido.'], 422);
            }

            $stmt = $pdo->prepare(
                "SELECT concluido FROM progresso_conteudos
                 WHERE usuario_id = ? AND conteudo_id = ?"
            );
            $stmt->execute([$uid, $conteudo]);
            $anterior = $stmt->fetch();

            $jaConcluido = $anterior && (int)$anterior['concluido'] === 1;
            $concluido = $percentual >= 100 ? 1 : 0;

            $stmt = $pdo->prepare(
                "INSERT INTO progresso_conteudos
                (usuario_id, conteudo_id, percentual, concluido)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    percentual = VALUES(percentual),
                    concluido = VALUES(concluido)"
            );
            $stmt->execute([$uid, $conteudo, $percentual, $concluido]);

            if ($concluido && !$jaConcluido) {
                $stmt = $pdo->prepare(
                    "INSERT INTO pontuacoes (usuario_id, quantidade, motivo)
                     VALUES (?, 10, 'Conteúdo concluído')"
                );
                $stmt->execute([$uid]);
            }

            jsonResponse([
                'ok' => true,
                'salvo' => true,
                'concluido' => $concluido
            ]);

            break;


        /* =========================
           DADOS DO PROGRESSO
        ========================= */

        case 'progresso_dados':

            $uid = requireLogin();
            requirePlanoPago($pdo, $uid);

            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(quantidade), 0) AS xp
                 FROM pontuacoes WHERE usuario_id = ?"
            );
            $stmt->execute([$uid]);
            $xp = (int)$stmt->fetch()['xp'];

            $nivel = max(1, (int)floor($xp / 250) + 1);

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM progresso_conteudos
                 WHERE usuario_id = ? AND concluido = 1"
            );
            $stmt->execute([$uid]);
            $conteudos = (int)$stmt->fetch()['total'];

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM tentativas WHERE usuario_id = ?"
            );
            $stmt->execute([$uid]);
            $simulados = (int)$stmt->fetch()['total'];

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM redacoes WHERE usuario_id = ?"
            );
            $stmt->execute([$uid]);
            $redacoes = (int)$stmt->fetch()['total'];

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) AS total FROM tarefas
                 WHERE usuario_id = ? AND status = 'concluida'"
            );
            $stmt->execute([$uid]);
            $tarefas = (int)$stmt->fetch()['total'];

            $atividades = $conteudos + $simulados + $redacoes + $tarefas;

            $stmt = $pdo->prepare(
                "SELECT DISTINCT DATE(data) AS dia
                 FROM pontuacoes WHERE usuario_id = ? ORDER BY dia DESC"
            );
            $stmt->execute([$uid]);
            $dias = $stmt->fetchAll();

            $sequencia = 0;
            $ultimaData = null;
            $hoje = new DateTime(date('Y-m-d'));

            foreach ($dias as $linha) {
                $dia = new DateTime($linha['dia']);

                if ($ultimaData === null) {
                    $diferencaHoje = (int)$hoje->diff($dia)->days;
                    if ($diferencaHoje > 1) break;
                } else {
                    $diferenca = (int)$ultimaData->diff($dia)->days;
                    if ($diferenca !== 1) break;
                }

                $sequencia++;
                $ultimaData = $dia;
            }

            $conquistasBase = [
                ['Primeiro passo', 'Concluiu seu primeiro conteúdo.', 20],
                ['Primeiro simulado', 'Finalizou seu primeiro simulado.', 30],
                ['Uma semana', 'Manteve uma sequência de 7 dias.', 50],
                ['500 XP', 'Alcançou 500 XP.', 100],
                ['Foco total', 'Realizou 10 atividades.', 100],
                ['Próximo nível', 'Alcançou o nível 2.', 100],
                ['1000 XP', 'Alcançou 1000 XP.', 200],
                ['Mestre BRIO', 'Concluiu grandes metas na plataforma.', 500]
            ];

            foreach ($conquistasBase as $conquista) {
                $stmt = $pdo->prepare(
                    "SELECT id FROM conquistas WHERE nome = ? LIMIT 1"
                );
                $stmt->execute([$conquista[0]]);

                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO conquistas (nome, descricao, xp)
                         VALUES (?, ?, ?)"
                    );
                    $stmt->execute($conquista);
                }
            }

            $regras = [
                'Primeiro passo' => $conteudos >= 1,
                'Primeiro simulado' => $simulados >= 1,
                'Uma semana' => $sequencia >= 7,
                '500 XP' => $xp >= 500,
                'Foco total' => $atividades >= 10,
                'Próximo nível' => $nivel >= 2,
                '1000 XP' => $xp >= 1000,
                'Mestre BRIO' => (
                    $xp >= 1000 &&
                    $conteudos >= 20 &&
                    $simulados >= 10 &&
                    $redacoes >= 5 &&
                    $tarefas >= 30
                )
            ];

            foreach ($regras as $nome => $desbloqueada) {
                if (!$desbloqueada) continue;

                $stmt = $pdo->prepare(
                    "SELECT id FROM conquistas WHERE nome = ? LIMIT 1"
                );
                $stmt->execute([$nome]);
                $conquista = $stmt->fetch();

                if ($conquista) {
                    $stmt = $pdo->prepare(
                        "INSERT IGNORE INTO usuario_conquistas
                         (usuario_id, conquista_id) VALUES (?, ?)"
                    );
                    $stmt->execute([$uid, $conquista['id']]);
                }
            }

            $stmt = $pdo->prepare(
                "SELECT c.id, c.nome, c.descricao, c.xp,
                        CASE WHEN uc.id IS NOT NULL THEN 1 ELSE 0 END AS desbloqueada
                 FROM conquistas c
                 LEFT JOIN usuario_conquistas uc
                    ON uc.conquista_id = c.id AND uc.usuario_id = ?
                 WHERE c.nome IN
                 ('Primeiro passo','Primeiro simulado','Uma semana','500 XP',
                  'Foco total','Próximo nível','1000 XP','Mestre BRIO')
                 ORDER BY FIELD(c.nome,
                  'Primeiro passo','Primeiro simulado','Uma semana','500 XP',
                  'Foco total','Próximo nível','1000 XP','Mestre BRIO')"
            );
            $stmt->execute([$uid]);
            $conquistas = $stmt->fetchAll();

            jsonResponse([
                'xp' => $xp,
                'nivel' => $nivel,
                'conteudos' => $conteudos,
                'simulados' => $simulados,
                'redacoes' => $redacoes,
                'tarefas' => $tarefas,
                'sequencia' => $sequencia,
                'atividades' => $atividades,
                'conquistas' => $conquistas
            ]);

            break;

        /* =========================
           SIMULADOS
        ========================= */

        
case 'simulados':

    requireLogin();

    $sql = "
        SELECT
            s.*,
            COALESCE(m.nome, 'Geral') AS materia,
            COUNT(q.id) AS questoes

        FROM simulados s

        LEFT JOIN materias m
            ON m.id = s.materia_id

        LEFT JOIN questoes q
            ON q.simulado_id = s.id

        GROUP BY s.id

        ORDER BY s.id DESC
    ";

    $resultado = $pdo->query($sql);

    jsonResponse($resultado->fetchAll());

    break;


        /* =========================
           SIMULADO INDIVIDUAL
        ========================= */

        
case 'simulado':

    requireLogin();

    $id = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            COALESCE(m.nome, 'Geral') AS materia

        FROM simulados s

        LEFT JOIN materias m
            ON m.id = s.materia_id

        WHERE s.id = ?
    ");

    $stmt->execute([$id]);

    $simulado = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$simulado) {
        jsonResponse([
            'erro' => 'Simulado não encontrado.'
        ], 404);
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            enunciado,
            alternativa_a,
            alternativa_b,
            alternativa_c,
            alternativa_d,
            alternativa_e

        FROM questoes

        WHERE simulado_id = ?

        ORDER BY id
    ");

    $stmt->execute([$id]);

    $simulado['questoes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse($simulado);

    break;



        /* =========================
           FINALIZAR SIMULADO
        ========================= */

        case 'finalizar_simulado':

            $uid =
                requireLogin();


            if ($method !== 'POST') {

                jsonResponse([
                    'erro' =>
                        'Método inválido.'
                ], 405);
            }


            $d =
                body();


            $sid =
                (int)(
                    $d['simulado_id'] ?? 0
                );


            $respostas =
                $d['respostas'] ?? [];


            $stmt =
                $pdo->prepare(
                    'SELECT *
                     FROM questoes
                     WHERE simulado_id = ?
                     ORDER BY id'
                );


            $stmt->execute([
                $sid
            ]);


            $questoes =
                $stmt->fetchAll();


            if (!$questoes) {

                jsonResponse([
                    'erro' =>
                        'Simulado sem questões.'
                ], 422);
            }


            $acertos = 0;
            $detalhes = [];


            foreach ($questoes as $questao) {

                $resposta =
                    $respostas[
                        (string)$questao['id']
                    ]
                    ??
                    $respostas[
                        $questao['id']
                    ]
                    ??
                    null;


                $correta =
                    $resposta &&
                    strtoupper($resposta) ===
                    strtoupper(
                        $questao[
                            'resposta_correta'
                        ]
                    );


                if ($correta) {
                    $acertos++;
                }


                $detalhes[] = [

                    'questao_id' =>
                        (int)$questao['id'],

                    'resposta' =>
                        $resposta,

                    'correta' =>
                        $correta
                ];
            }


            $total =
                count($questoes);


            $erros =
                $total - $acertos;


            $percentual =
                round(
                    (
                        $acertos / $total
                    ) * 100,
                    2
                );


            $xp =
                ($acertos * 5) + 30;


            $stmt =
                $pdo->prepare(
                    'INSERT INTO tentativas
                    (
                        usuario_id,
                        simulado_id,
                        acertos,
                        erros,
                        percentual,
                        xp_ganho
                    )
                    VALUES (?, ?, ?, ?, ?, ?)'
                );


            $stmt->execute([
                $uid,
                $sid,
                $acertos,
                $erros,
                $percentual,
                $xp
            ]);


            $tentativaId =
                (int)$pdo->lastInsertId();


            $stmt =
                $pdo->prepare(
                    'INSERT INTO respostas
                    (
                        tentativa_id,
                        questao_id,
                        resposta,
                        correta
                    )
                    VALUES (?, ?, ?, ?)'
                );


            foreach ($detalhes as $item) {

                $stmt->execute([

                    $tentativaId,

                    $item['questao_id'],

                    $item['resposta'],

                    $item['correta']
                        ? 1
                        : 0
                ]);
            }


            $stmt =
                $pdo->prepare(
                    'INSERT INTO pontuacoes
                    (
                        usuario_id,
                        quantidade,
                        motivo
                    )
                    VALUES (?, ?, ?)'
                );


            $stmt->execute([
                $uid,
                $xp,
                'Simulado concluído'
            ]);


            jsonResponse([

                'ok' =>
                    true,

                'tentativa_id' =>
                    $tentativaId,

                'acertos' =>
                    $acertos,

                'erros' =>
                    $erros,

                'percentual' =>
                    $percentual,

                'xp' =>
                    $xp
            ]);

            break;


        /* =========================
           GABARITO
        ========================= */

        case 'gabarito':

            $uid =
                requireLogin();


            $tid =
                (int)(
                    $_GET['tentativa_id'] ?? 0
                );


            $stmt =
                $pdo->prepare(
                    'SELECT
                        t.*,
                        s.titulo

                     FROM tentativas t

                     JOIN simulados s
                        ON s.id = t.simulado_id

                     WHERE t.id = ?
                     AND t.usuario_id = ?'
                );


            $stmt->execute([
                $tid,
                $uid
            ]);


            $tentativa =
                $stmt->fetch();


            if (!$tentativa) {

                jsonResponse([
                    'erro' =>
                        'Tentativa não encontrada.'
                ], 404);
            }


            $stmt =
                $pdo->prepare(
                    'SELECT
                        q.id,
                        q.enunciado,
                        q.resposta_correta,
                        q.comentario,
                        r.resposta,
                        r.correta

                     FROM respostas r

                     JOIN questoes q
                        ON q.id = r.questao_id

                     WHERE r.tentativa_id = ?

                     ORDER BY q.id'
                );


            $stmt->execute([
                $tid
            ]);


            $tentativa['questoes'] =
                $stmt->fetchAll();


            jsonResponse(
                $tentativa
            );

            break;
            case 'registrar_jogo':

    $uid = requireLogin();
    requirePlanoPago($pdo, $uid);

    if ($method !== 'POST') {
        jsonResponse([
            'erro' => 'Método inválido.'
        ], 405);
    }

    $d = body();

    $pontos = (int)($d['pontos'] ?? 0);

    // Não permite pontuação negativa
    $pontos = max(0, $pontos);

    // Limite para evitar valores absurdos
    $pontos = min(100, $pontos);

    if ($pontos > 0) {

        $stmt = $pdo->prepare(
            "INSERT INTO pontuacoes
            (
                usuario_id,
                quantidade,
                motivo
            )
            VALUES (?, ?, 'Jogo BRIO')"
        );

        $stmt->execute([
            $uid,
            $pontos
        ]);
    }

    // Retorna o XP total atualizado
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantidade), 0) AS xp
         FROM pontuacoes
         WHERE usuario_id = ?"
    );

    $stmt->execute([$uid]);

    $xp = (int)$stmt->fetch()['xp'];

    $nivel = max(
        1,
        (int)floor($xp / 250) + 1
    );

    jsonResponse([
        'ok' => true,
        'pontos_jogo' => $pontos,
        'xp' => $xp,
        'nivel' => $nivel
    ]);

    break;


        /* =========================
           TEMAS DE REDAÇÃO
        ========================= */

        case 'temas_redacao':

    $uid = requireLogin();

    // Verifica se o aluno possui plano pago ativo
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM usuarios u
        INNER JOIN planos p
            ON p.id = u.plano_atual_id
        INNER JOIN assinaturas a
            ON a.usuario_id = u.id
            AND a.plano_id = p.id
        WHERE u.id = ?
          AND p.valor_mensal > 0
          AND a.status = 'ativa'
          AND a.fim >= CURDATE()
    ");

    $stmt->execute([$uid]);

    $premium = (int)$stmt->fetchColumn() > 0;

    if ($premium) {

        // Premium: todos os temas
        $stmt = $pdo->query("
            SELECT *
            FROM temas_redacao
            ORDER BY id DESC
        ");

    } else {

        // Gratuito: apenas os 3 primeiros temas cadastrados
        $stmt = $pdo->query("
            SELECT *
            FROM temas_redacao
            ORDER BY id ASC
            LIMIT 3
        ");

    }

    jsonResponse($stmt->fetchAll());

    break;


        /* =========================
           ENVIAR REDAÇÃO
        ========================= */

        case 'enviar_redacao':

    $uid = requireLogin();

    if ($method !== 'POST') {
        jsonResponse([
            'erro' => 'Método inválido.'
        ], 405);
    }

    $d = body();

    $tema = (int)($d['tema_id'] ?? 0);
    $texto = trim($d['texto'] ?? '');

    // Validação da redação
    if ($tema <= 0 || strlen($texto) < 50) {
        jsonResponse([
            'erro' => 'Escolha um tema e escreva uma redação com pelo menos 50 caracteres.'
        ], 422);
    }

    // Verifica se o tema existe no banco
    $stmt = $pdo->prepare("
        SELECT id
        FROM temas_redacao
        WHERE id = ?
    ");

    $stmt->execute([$tema]);

    if (!$stmt->fetchColumn()) {
        jsonResponse([
            'erro' => 'Tema de redação não encontrado.'
        ], 404);
    }

    // Verifica se o aluno possui plano Premium ativo
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM usuarios u
        INNER JOIN planos p
            ON p.id = u.plano_atual_id
        INNER JOIN assinaturas a
            ON a.usuario_id = u.id
            AND a.plano_id = p.id
        WHERE u.id = ?
          AND p.valor_mensal > 0
          AND a.status = 'ativa'
          AND a.fim >= CURDATE()
    ");

    $stmt->execute([$uid]);

    $premium = (int)$stmt->fetchColumn() > 0;

    // Se não for Premium, verifica se o tema está entre os 3 gratuitos
    if (!$premium) {

        $stmt = $pdo->query("
            SELECT id
            FROM temas_redacao
            ORDER BY id ASC
            LIMIT 3
        ");

        $temasGratuitos = array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );

        if (!in_array($tema, $temasGratuitos, true)) {
            jsonResponse([
                'erro' => 'Este tema é exclusivo para assinantes Premium.'
            ], 403);
        }
    }

    // Salva a redação no banco
    $stmt = $pdo->prepare("
        INSERT INTO redacoes
            (usuario_id, tema_id, texto)
        VALUES
            (?, ?, ?)
    ");

    $stmt->execute([
        $uid,
        $tema,
        $texto
    ]);

    // Adiciona XP pela redação enviada
    $stmt = $pdo->prepare("
        INSERT INTO pontuacoes
            (usuario_id, quantidade, motivo)
        VALUES
            (?, 25, 'Redação enviada')
    ");

    $stmt->execute([$uid]);

    jsonResponse([
        'ok' => true,
        'mensagem' => 'Redação enviada para correção.'
    ], 201);

    break;


        /* =========================
           MINHAS REDAÇÕES
        ========================= */

        case 'minhas_redacoes':

            $uid =
                requireLogin();


            $sql = "
                SELECT
                    r.*,
                    t.titulo AS tema,
                    c.nota,
                    c.comentario,
                    c.data_correcao

                FROM redacoes r

                JOIN temas_redacao t
                    ON t.id = r.tema_id

                LEFT JOIN correcoes c
                    ON c.redacao_id = r.id

                WHERE r.usuario_id = ?

                ORDER BY r.id DESC
            ";


            $stmt =
                $pdo->prepare($sql);

            $stmt->execute([
                $uid
            ]);


            jsonResponse(
                $stmt->fetchAll()
            );

            break;


        /* =========================
           REDAÇÕES DO PROFESSOR
        ========================= */

        
case 'redacoes_professor':

    requireProfessor();

    $stmt = $pdo->query("
        SELECT
            r.id,
            r.texto,
            r.status,
            r.data_envio,
            u.nome AS aluno,
            t.titulo AS tema
        FROM redacoes r
        JOIN usuarios u
            ON u.id = r.usuario_id
        JOIN temas_redacao t
            ON t.id = r.tema_id
        WHERE NOT EXISTS (
            SELECT 1
            FROM correcoes c
            WHERE c.redacao_id = r.id
        )
        ORDER BY r.data_envio ASC, r.id ASC
    ");

    jsonResponse($stmt->fetchAll());

    break;

    
/* =====================================
   HISTÓRICO DE CORREÇÕES DO PROFESSOR
===================================== */

case 'minhas_correcoes_professor':

    $professorId = requireProfessor();

    $stmt = $pdo->prepare("
        SELECT
            c.id AS correcao_id,
            r.id AS redacao_id,
            r.texto,
            r.data_envio,
            u.nome AS aluno,
            t.titulo AS tema,
            c.nota,
            c.comentario,
            c.data_correcao
        FROM correcoes c
        INNER JOIN redacoes r
            ON r.id = c.redacao_id
        INNER JOIN usuarios u
            ON u.id = r.usuario_id
        INNER JOIN temas_redacao t
            ON t.id = r.tema_id
        WHERE c.professor_id = ?
        ORDER BY c.data_correcao DESC, c.id DESC
    ");

    $stmt->execute([$professorId]);

    jsonResponse($stmt->fetchAll());

    break;



        /* =========================
           CORRIGIR REDAÇÃO
        ========================= */

        
case 'corrigir_redacao':

    $professorId = requireProfessor();

    if ($method !== 'POST') {
        jsonResponse([
            'erro' => 'Método inválido.'
        ], 405);
    }

    $d = body();

    $redacaoId = (int)($d['redacao_id'] ?? 0);
    $notaInformada = $d['nota'] ?? null;
    $comentario = trim($d['comentario'] ?? '');

    if (
        $redacaoId <= 0 ||
        !is_numeric($notaInformada) ||
        (float)$notaInformada < 0 ||
        (float)$notaInformada > 1000 ||
        $comentario === ''
    ) {
        jsonResponse([
            'erro' => 'Informe uma nota entre 0 e 1000 e um comentário.'
        ], 422);
    }

    $nota = (float)$notaInformada;

    $pdo->beginTransaction();

    try {

        // Bloqueia a redação durante a correção.
        $stmt = $pdo->prepare("
            SELECT id
            FROM redacoes
            WHERE id = ?
            FOR UPDATE
        ");

        $stmt->execute([$redacaoId]);

        if (!$stmt->fetch()) {
            $pdo->rollBack();

            jsonResponse([
                'erro' => 'Redação não encontrada.'
            ], 404);
        }

        // Verifica se outro professor já corrigiu.
        $stmt = $pdo->prepare("
            SELECT id
            FROM correcoes
            WHERE redacao_id = ?
            LIMIT 1
        ");

        $stmt->execute([$redacaoId]);

        if ($stmt->fetch()) {
            $pdo->rollBack();

            jsonResponse([
                'erro' => 'Esta redação já foi corrigida por outro professor.'
            ], 409);
        }

        // Registra a correção e seu autor.
        $stmt = $pdo->prepare("
            INSERT INTO correcoes
                (redacao_id, professor_id, nota, comentario)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $redacaoId,
            $professorId,
            $nota,
            $comentario
        ]);

        // Atualiza a redação.
        $stmt = $pdo->prepare("
            UPDATE redacoes
            SET status = 'corrigida'
            WHERE id = ?
        ");

        $stmt->execute([$redacaoId]);

        // Mantém os 40 XP existentes no BRIO.
        $stmt = $pdo->prepare("
            INSERT INTO pontuacoes
                (usuario_id, quantidade, motivo)
            SELECT
                usuario_id,
                40,
                'Redação corrigida'
            FROM redacoes
            WHERE id = ?
        ");

        $stmt->execute([$redacaoId]);

        $pdo->commit();

        jsonResponse([
            'ok' => true,
            'mensagem' => 'Correção salva com sucesso!'
        ]);

    } catch (Throwable $erro) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $erro;
    }

    break;



        /* =========================
           TAREFAS
        ========================= */

        case 'tarefas':

            $uid =
                requireLogin();

            requirePlanoPago($pdo, $uid);


            if ($method === 'GET') {

                $stmt =
                    $pdo->prepare(
                        'SELECT *
                         FROM tarefas
                         WHERE usuario_id = ?
                         ORDER BY data, horario'
                    );


                $stmt->execute([
                    $uid
                ]);


                jsonResponse(
                    $stmt->fetchAll()
                );
            }


            if ($method === 'POST') {

                $d =
                    body();


                $titulo =
                    trim(
                        $d['titulo'] ?? ''
                    );


                if ($titulo === '') {

                    jsonResponse([
                        'erro' =>
                            'Informe o título da tarefa.'
                    ], 422);
                }


                $stmt =
                    $pdo->prepare(
                        'INSERT INTO tarefas
                        (
                            usuario_id,
                            titulo,
                            descricao,
                            data,
                            horario,
                            duracao,
                            tipo
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );


                $stmt->execute([

                    $uid,

                    $titulo,

                    trim(
                        $d['descricao'] ?? ''
                    ),

                    $d['data'] ??
                        date('Y-m-d'),

                    $d['horario'] ??
                        null,

                    (int)(
                        $d['duracao'] ?? 0
                    ),

                    $d['tipo'] ??
                        'estudo'
                ]);


                jsonResponse([
                    'ok' => true,
                    'id' =>
                        $pdo->lastInsertId()
                ], 201);
            }


            jsonResponse([
                'erro' =>
                    'Método inválido.'
            ], 405);

            break;


        /* =========================
           CONCLUIR TAREFA
        ========================= */

        case 'concluir_tarefa':

            $uid =
                requireLogin();


            if ($method !== 'POST') {

                jsonResponse([
                    'erro' =>
                        'Método inválido.'
                ], 405);
            }


            $d =
                body();


            $id =
                (int)(
                    $d['id'] ?? 0
                );


            $stmt =
                $pdo->prepare(
                    "UPDATE tarefas
                     SET status = 'concluida'
                     WHERE id = ?
                     AND usuario_id = ?"
                );


            $stmt->execute([
                $id,
                $uid
            ]);


            if ($stmt->rowCount()) {

                $stmt =
                    $pdo->prepare(
                        "INSERT INTO pontuacoes
                        (
                            usuario_id,
                            quantidade,
                            motivo
                        )
                        VALUES (?, 10, 'Tarefa concluída')"
                    );


                $stmt->execute([
                    $uid
                ]);
            }


            jsonResponse([
                'ok' => true
            ]);

            break;


        /* =========================
           RANKING
        ========================= */

        case 'ranking':

            requireLogin();


            $sql = "
                SELECT
                    u.id,
                    u.nome,
                    COALESCE(
                        SUM(p.quantidade),
                        0
                    ) AS xp

                FROM usuarios u

                LEFT JOIN pontuacoes p
                    ON p.usuario_id = u.id

                WHERE u.tipo = 'aluno'

                GROUP BY u.id

                ORDER BY
                    xp DESC,
                    u.nome ASC

                LIMIT 20
            ";


            $resultado =
                $pdo->query($sql);


            $linhas =
                $resultado->fetchAll();


            foreach (
                $linhas as $indice => &$linha
            ) {

                $linha['posicao'] =
                    $indice + 1;

                $linha['nivel'] =
                    max(
                        1,
                        (int)floor(
                            $linha['xp'] / 250
                        ) + 1
                    );
            }


            jsonResponse($linhas);

            break;


        /* =========================
           PLANOS
        ========================= */

        case 'planos':

            requireLogin();


            $resultado =
                $pdo->query(
                    'SELECT *
                     FROM planos
                     WHERE ativo = 1
                     ORDER BY valor_mensal DESC'
                );


            jsonResponse(
                $resultado->fetchAll()
            );

            break;


        /* =========================
           ASSINAR PLANO
        ========================= */

        case 'assinar':

            $uid =
                requireLogin();


            if ($method !== 'POST') {

                jsonResponse([
                    'erro' =>
                        'Método inválido.'
                ], 405);
            }


            $d =
                body();


            $planoId =
                (int)(
                    $d['plano_id'] ?? 0
                );


            $stmt =
                $pdo->prepare(
                    'SELECT *
                     FROM planos
                     WHERE id = ?
                     AND ativo = 1'
                );


            $stmt->execute([
                $planoId
            ]);


            $plano =
                $stmt->fetch();


            if (!$plano) {

                jsonResponse([
                    'erro' =>
                        'Plano inválido.'
                ], 422);
            }


            $inicio =
                date('Y-m-d');


            $fim =
                date(
                    'Y-m-d',
                    strtotime(
                        '+' .
                        $plano['periodo_meses'] .
                        ' months'
                    )
                );


            $stmt =
                $pdo->prepare(
                    "UPDATE assinaturas
                     SET status = 'cancelada'
                     WHERE usuario_id = ?
                     AND status = 'ativa'"
                );


            $stmt->execute([
                $uid
            ]);


            $stmt =
                $pdo->prepare(
                    'INSERT INTO assinaturas
                    (
                        usuario_id,
                        plano_id,
                        inicio,
                        fim
                    )
                    VALUES (?, ?, ?, ?)'
                );


            $stmt->execute([
                $uid,
                $planoId,
                $inicio,
                $fim
            ]);


            $stmt =
                $pdo->prepare(
                    'UPDATE usuarios
                     SET plano_atual_id = ?
                     WHERE id = ?'
                );


            $stmt->execute([
                $planoId,
                $uid
            ]);


            jsonResponse([
                'ok' => true,
                'mensagem' =>
                    'Plano contratado. O pagamento é apenas uma simulação.'
            ]);

            break;


        /* =========================
           PERFIL
        ========================= */

        case 'perfil':

            $uid =
                requireLogin();


            $stmt =
                $pdo->prepare(
                    'SELECT
                        u.id,
                        u.nome,
                        u.email,
                        u.tipo,
                        u.objetivo,
                        u.criado_em,
                        p.nome AS plano,
                        p.valor_mensal

                     FROM usuarios u

                     LEFT JOIN planos p
                        ON p.id = u.plano_atual_id

                     WHERE u.id = ?'
                );


            $stmt->execute([
                $uid
            ]);


            $usuario =
                $stmt->fetch();


            if (!$usuario) {

                jsonResponse([
                    'erro' =>
                        'Usuário não encontrado.'
                ], 404);
            }


            $stmt =
                $pdo->prepare(
                    'SELECT
                        COALESCE(
                            SUM(quantidade),
                            0
                        ) AS xp

                     FROM pontuacoes

                     WHERE usuario_id = ?'
                );


            $stmt->execute([
                $uid
            ]);


            $usuario['xp'] =
                (int)$stmt->fetch()['xp'];


            $usuario['nivel'] =
                max(
                    1,
                    (int)floor(
                        $usuario['xp'] / 250
                    ) + 1
                );


            jsonResponse(
                $usuario
            );

            break;


        /* =========================
           AÇÃO NÃO EXISTE
        ========================= */

        default:

            jsonResponse([
                'erro' =>
                    'Ação não encontrada.'
            ], 404);
    }


} catch (PDOException $erro) {

    /*
     * Se o problema for o MySQL/banco,
     * agora o navegador recebe JSON.
     */

    jsonResponse([

        'erro' =>
            'Não foi possível conectar ou executar uma operação no banco de dados.',

        'detalhe' =>
            $erro->getMessage()

    ], 500);


} catch (Throwable $erro) {

    jsonResponse([

        'erro' =>
            'Erro no servidor.',

        'detalhe' =>
            $erro->getMessage()

    ], 500);
}

?>