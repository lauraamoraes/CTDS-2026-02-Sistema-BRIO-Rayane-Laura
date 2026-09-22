<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/response.php';
require_once __DIR__ . '/config/auth.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {

    /*
     * A conexão fica DENTRO do try.
     * Assim, se o MySQL estiver com problema,
     * o servidor ainda consegue devolver JSON.
     */
    $pdo = Database::connect();


    switch ($action) {


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

            session_destroy();

            jsonResponse([
                'ok' => true
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


            $conteudo =
                (int)(
                    $d['conteudo_id'] ?? 0
                );


            $percentual =
                min(
                    100,
                    max(
                        0,
                        (int)(
                            $d['percentual'] ?? 0
                        )
                    )
                );


            $concluido =
                $percentual >= 100
                    ? 1
                    : 0;


            $stmt =
                $pdo->prepare(
                    "INSERT INTO progresso_conteudos
                    (
                        usuario_id,
                        conteudo_id,
                        percentual,
                        concluido
                    )
                    VALUES (?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE
                        percentual = VALUES(percentual),
                        concluido = VALUES(concluido)"
                );


            $stmt->execute([
                $uid,
                $conteudo,
                $percentual,
                $concluido
            ]);


            if ($concluido) {

                $stmt =
                    $pdo->prepare(
                        "INSERT INTO pontuacoes
                        (
                            usuario_id,
                            quantidade,
                            motivo
                        )
                        VALUES (?, 10, 'Conteúdo concluído')"
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
           SIMULADOS
        ========================= */

        case 'simulados':

            requireLogin();


            $sql = "
                SELECT
                    s.*,
                    m.nome AS materia,
                    COUNT(q.id) AS questoes

                FROM simulados s

                JOIN materias m
                    ON m.id = s.materia_id

                LEFT JOIN questoes q
                    ON q.simulado_id = s.id

                GROUP BY s.id

                ORDER BY s.id DESC
            ";


            $resultado =
                $pdo->query($sql);


            jsonResponse(
                $resultado->fetchAll()
            );

            break;


        /* =========================
           SIMULADO INDIVIDUAL
        ========================= */

        case 'simulado':

            requireLogin();


            $id =
                (int)(
                    $_GET['id'] ?? 0
                );


            $stmt =
                $pdo->prepare(
                    'SELECT
                        s.*,
                        m.nome AS materia

                     FROM simulados s

                     JOIN materias m
                        ON m.id = s.materia_id

                     WHERE s.id = ?'
                );


            $stmt->execute([
                $id
            ]);


            $simulado =
                $stmt->fetch();


            if (!$simulado) {

                jsonResponse([
                    'erro' =>
                        'Simulado não encontrado.'
                ], 404);
            }


            $stmt =
                $pdo->prepare(
                    'SELECT
                        id,
                        enunciado,
                        alternativa_a,
                        alternativa_b,
                        alternativa_c,
                        alternativa_d,
                        alternativa_e

                     FROM questoes

                     WHERE simulado_id = ?

                     ORDER BY id'
                );


            $stmt->execute([
                $id
            ]);


            $simulado['questoes'] =
                $stmt->fetchAll();


            jsonResponse(
                $simulado
            );

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


        /* =========================
           TEMAS DE REDAÇÃO
        ========================= */

        case 'temas_redacao':

            requireLogin();


            $resultado =
                $pdo->query(
                    'SELECT *
                     FROM temas_redacao
                     ORDER BY id DESC'
                );


            jsonResponse(
                $resultado->fetchAll()
            );

            break;


        /* =========================
           ENVIAR REDAÇÃO
        ========================= */

        case 'enviar_redacao':

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


            $tema =
                (int)(
                    $d['tema_id'] ?? 0
                );


            $texto =
                trim(
                    $d['texto'] ?? ''
                );


            if (
                $tema <= 0 ||
                strlen($texto) < 50
            ) {

                jsonResponse([
                    'erro' =>
                        'Escolha um tema e escreva uma redação com pelo menos 50 caracteres.'
                ], 422);
            }


            $stmt =
                $pdo->prepare(
                    'INSERT INTO redacoes
                    (
                        usuario_id,
                        tema_id,
                        texto
                    )
                    VALUES (?, ?, ?)'
                );


            $stmt->execute([
                $uid,
                $tema,
                $texto
            ]);


            $stmt =
                $pdo->prepare(
                    "INSERT INTO pontuacoes
                    (
                        usuario_id,
                        quantidade,
                        motivo
                    )
                    VALUES (?, 25, 'Redação enviada')"
                );


            $stmt->execute([
                $uid
            ]);


            jsonResponse([
                'ok' => true,
                'mensagem' =>
                    'Redação enviada para correção.'
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


            $sql = "
                SELECT
                    r.id,
                    r.texto,
                    r.status,
                    r.data_envio,
                    u.nome AS aluno,
                    t.titulo AS tema,
                    c.nota,
                    c.comentario

                FROM redacoes r

                JOIN usuarios u
                    ON u.id = r.usuario_id

                JOIN temas_redacao t
                    ON t.id = r.tema_id

                LEFT JOIN correcoes c
                    ON c.redacao_id = r.id

                ORDER BY
                    r.status = 'enviada' DESC,
                    r.id DESC
            ";


            $resultado =
                $pdo->query($sql);


            jsonResponse(
                $resultado->fetchAll()
            );

            break;


        /* =========================
           CORRIGIR REDAÇÃO
        ========================= */

        case 'corrigir_redacao':

            $professorId =
                requireProfessor();


            if ($method !== 'POST') {

                jsonResponse([
                    'erro' =>
                        'Método inválido.'
                ], 405);
            }


            $d =
                body();


            $redacaoId =
                (int)(
                    $d['redacao_id'] ?? 0
                );


            $nota =
                (float)(
                    $d['nota'] ?? 0
                );


            $comentario =
                trim(
                    $d['comentario'] ?? ''
                );


            if (
                $nota < 0 ||
                $nota > 1000 ||
                $comentario === ''
            ) {

                jsonResponse([
                    'erro' =>
                        'Informe nota entre 0 e 1000 e um comentário.'
                ], 422);
            }


            $stmt =
                $pdo->prepare(
                    'INSERT INTO correcoes
                    (
                        redacao_id,
                        professor_id,
                        nota,
                        comentario
                    )
                    VALUES (?, ?, ?, ?)

                    ON DUPLICATE KEY UPDATE
                        professor_id =
                            VALUES(professor_id),

                        nota =
                            VALUES(nota),

                        comentario =
                            VALUES(comentario),

                        data_correcao =
                            NOW()'
                );


            $stmt->execute([
                $redacaoId,
                $professorId,
                $nota,
                $comentario
            ]);


            $stmt =
                $pdo->prepare(
                    "UPDATE redacoes
                     SET status = 'corrigida'
                     WHERE id = ?"
                );


            $stmt->execute([
                $redacaoId
            ]);


            $stmt =
                $pdo->prepare(
                    "INSERT INTO pontuacoes
                    (
                        usuario_id,
                        quantidade,
                        motivo
                    )

                    SELECT
                        usuario_id,
                        40,
                        'Redação corrigida'

                    FROM redacoes

                    WHERE id = ?"
                );


            $stmt->execute([
                $redacaoId
            ]);


            jsonResponse([
                'ok' => true,
                'mensagem' =>
                    'Correção salva.'
            ]);

            break;


        /* =========================
           TAREFAS
        ========================= */

        case 'tarefas':

            $uid =
                requireLogin();


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