const API = '../backend/api.php';

/* =========================
   COMUNICAÇÃO COM A API
========================= */

async function api(action, options = {}) {
    const response = await fetch(`${API}?action=${action}`, {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        ...options
    });

    const texto = await response.text();
    let data;

    try {
        data = JSON.parse(texto);
    } catch (erro) {
        console.error('Resposta recebida pelo PHP:', texto);
        throw new Error(
            'O servidor não retornou um JSON válido. Verifique o PHP e o banco de dados.'
        );
    }

    if (!response.ok) {
        throw new Error(data.erro || 'Erro inesperado no servidor.');
    }

    return data;
}

/* =========================
   FUNÇÕES AUXILIARES
========================= */

function qs(id) {
    return document.getElementById(id);
}

function esc(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, caractere => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[caractere]);
}

function setMsg(id, texto, mostrar = true) {
    const elemento = qs(id);
    if (!elemento) return;

    elemento.textContent = texto;
    elemento.style.display = mostrar ? 'block' : 'none';
}

/* =========================
   USUÁRIO
========================= */

async function requireUser() {
    try {
        const dados = await api('me');

        if (!dados.usuario) {
            window.location.href = 'login.html';
            return null;
        }

        return dados.usuario;

    } catch (erro) {
        console.error(erro);
        window.location.href = 'login.html';
        return null;
    }
}
// Verifica se o usuário possui um plano pago
async function requirePaidPlan() {

    const user = await requireUser();

    if (!user) {
        window.location.href = 'login.html';
        return false;
    }

    try {

        const dados = await api('verificar_plano');

        if (!dados.pago) {

            alert(
                'Essa funcionalidade está disponível apenas para assinantes. Escolha um plano para continuar!'
            );

            window.location.replace('dashboard.html');

            return false;
        }

        return true;

    } catch (erro) {

        console.error(erro);

        alert('Não foi possível verificar seu plano.');

        window.location.replace('dashboard.html');

        return false;
    }
}

async function logout() {
    try {
        await api('logout', { method: 'POST' });
    } catch (erro) {
        console.error(erro);
    }

    window.location.href = 'index.html';
}

/* =========================
   CABEÇALHO
========================= */

function header() {
    const elemento = qs('header');
    if (!elemento) return;

    elemento.innerHTML = `
        <div class="topbar">

            <a class="logo" href="dashboard.html">
                BRIO
                <span>foco • constância</span>
            </a>

            <nav class="nav">
                <a href="dashboard.html">Início</a>
                <a href="conteudos.html">Conteúdos</a>
                <a href="simulados.html">Simulados</a>
                <a href="redacoes.html">Redações</a>
                <a href="calendario.html">Calendário</a>
                <a href="ranking.html">Ranking</a>
                <a href="planos.html">Planos</a>
                <a href="perfil.html">Perfil</a>
                <button onclick="logout()">Sair</button>
            </nav>

        </div>
    `;
}

/* =========================
   DASHBOARD
========================= */

async function initDashboard() {
    const user = await requireUser();
    if (!user) return;

    header();
    const boasVindas = document.getElementById("boasVindasProfessor");

if (boasVindas) {
    const primeiroNome = String(user.nome || "Professor").trim().split(/\s+/)[0];

    boasVindas.textContent = `Bem-vindo(a), ${primeiroNome}! 💜`;
}

    qs('welcome').textContent =
        'Olá, ' + user.nome.split(' ')[0] + '!';

    const dados = await api('dashboard');
    // Verifica se o usuário possui plano pago
const plano = await api('verificar_plano');

const cardsBloqueados = [
    'cardProgresso',
    'cardJogo',
    'cardCalendario'
];

if (!plano.pago) {
    cardsBloqueados.forEach(id => {
        const card = qs(id);

        if (card) {
            card.classList.add('quadro-bloqueado');
        }
    });
}

    qs('xp').textContent = dados.xp;
    qs('nivel').textContent = dados.nivel;
    qs('simulados').textContent = dados.simulados;
    qs('redacoes').textContent = dados.redacoes;

    if (dados.tarefas.length > 0) {

        qs('tarefas').innerHTML = dados.tarefas.map(tarefa => `
            <div class="card">
                <b>${esc(tarefa.titulo)}</b>

                <div class="muted small">
                    ${esc(tarefa.data)}
                    ${tarefa.horario ? esc(tarefa.horario) : ''}
                </div>

                <span class="badge">
                    ${esc(tarefa.status)}
                </span>

                ${tarefa.status === 'pendente' ? `
                    <button
                        class="btn green small"
                        onclick="concluir(${Number(tarefa.id)})"
                    >
                        Concluir
                    </button>
                ` : ''}
            </div>
        `).join('');

    } else {

        qs('tarefas').innerHTML =
            '<div class="empty">Nenhuma atividade cadastrada.</div>';
    }

    // Verifica se o usuário possui plano pago
    try {
        const plano = await api('verificar_plano');

        if (!plano.pago) {
            bloquearFuncoes();
        }

    } catch (erro) {
        console.error('Erro ao verificar plano:', erro);
        bloquearFuncoes();
    }
}

function bloquearFuncoes() {

    const funcoes = [
        {
            id: 'cardProgresso',
            pagina: 'progresso.html'
        },
        {
            id: 'cardJogo',
            pagina: 'jogo.html'
        },
        {
            id: 'cardCalendario',
            pagina: 'calendario.html'
        }
    ];

    funcoes.forEach(funcao => {

        const card = qs(funcao.id);

        if (!card) return;

        card.classList.add('bloqueado');

        card.addEventListener('click', function(event) {

            event.preventDefault();

            alert(
                'Essa funcionalidade está disponível apenas para assinantes. Escolha um plano para continuar!'
            );

            window.location.replace('dashboard.html');

        });
    });
}

async function concluir(id) {

    await api('concluir_tarefa', {
        method: 'POST',
        body: JSON.stringify({ id })
    });

    window.location.reload();
}

/* =========================
   CONTEÚDOS
========================= */

const MATERIAS_BRIO = [
    'Língua Portuguesa',
    'Língua Estrangeira (Inglês/Espanhol)',
    'Matemática',
    'Biologia',
    'Química',
    'Física',
    'História',
    'Geografia',
    'Filosofia',
    'Sociologia'
];

let todosConteudos = [];

async function initConteudos() {

    const user = await requireUser();
    if (!user) return;

    header();

    try {

        todosConteudos = await api('conteudos');
        mostrarMaterias();

    } catch (erro) {

        console.error(erro);

        qs('lista-materias').innerHTML = `
            <div class="empty">
                Não foi possível carregar as matérias.
            </div>
        `;

    }
}

function mostrarMaterias() {

    const lista = qs('lista-materias');

    lista.innerHTML = MATERIAS_BRIO.map(materia => `
        <button
            class="materia-card"
            onclick="selecionarMateria('${esc(materia)}')"
        >

            <div class="materia-icone">
                ${iconeMateria(materia)}
            </div>

            <div>
                <h3>${esc(materia)}</h3>
                <p>Ver conteúdos</p>
            </div>

            <span class="materia-seta">→</span>

        </button>
    `).join('');

}

function selecionarMateria(materia) {

    qs('selecao-materias').style.display = 'none';
    qs('conteudos-materia').style.display = 'block';

    qs('materia-badge').textContent = materia;
    qs('titulo-materia').textContent = materia;

    mostrarConteudosMateria(materia);

}

function mostrarConteudosMateria(materia) {

    const lista = qs('lista');
    const nomesBanco = obterNomesBanco(materia);

    const conteudos = todosConteudos.filter(conteudo =>
        nomesBanco.includes(conteudo.materia)
    );

    if (conteudos.length === 0) {

        lista.innerHTML = `
            <div class="empty">

                <h3>Nenhum conteúdo cadastrado</h3>

                <p>
                    Ainda não existem conteúdos disponíveis
                    para ${esc(materia)}.
                </p>

            </div>
        `;

        return;
    }

    lista.innerHTML = conteudos.map(conteudo => `
        <article class="card">

            <span class="badge">
                ${esc(conteudo.materia)}
            </span>

            <h3>${esc(conteudo.titulo)}</h3>

            <p>${esc(conteudo.descricao)}</p>

            <p class="small">
                ${esc(conteudo.texto)}
            </p>

            <div class="progress">
                <span style="width:${Number(conteudo.percentual)}%"></span>
            </div>

            <p class="small">
                Progresso: ${Number(conteudo.percentual)}%
            </p>

            <button
                class="btn"
                onclick="marcarConteudo(
                    ${Number(conteudo.id)},
                    ${Number(conteudo.percentual) >= 100 ? 0 : 100}
                )"
            >
                ${Number(conteudo.percentual) >= 100
                    ? 'Refazer'
                    : 'Concluir conteúdo'}
            </button>

        </article>
    `).join('');

}

function voltarMaterias() {

    qs('conteudos-materia').style.display = 'none';
    qs('selecao-materias').style.display = 'block';

}

function obterNomesBanco(materia) {

    const equivalencias = {

        'Língua Portuguesa': [
            'Língua Portuguesa',
            'Português'
        ],

        'Língua Estrangeira (Inglês/Espanhol)': [
            'Língua Estrangeira (Inglês/Espanhol)',
            'Inglês',
            'Espanhol'
        ],

        'Matemática': ['Matemática'],
        'Biologia': ['Biologia'],
        'Química': ['Química'],
        'Física': ['Física'],
        'História': ['História'],
        'Geografia': ['Geografia'],
        'Filosofia': ['Filosofia'],
        'Sociologia': ['Sociologia']

    };

    return equivalencias[materia] || [materia];

}

function iconeMateria(materia) {

    const icones = {

        'Língua Portuguesa': '📚',
        'Língua Estrangeira (Inglês/Espanhol)': '🌎',
        'Matemática': '📐',
        'Biologia': '🧬',
        'Química': '⚗️',
        'Física': '⚛️',
        'História': '🏛️',
        'Geografia': '🌍',
        'Filosofia': '💭',
        'Sociologia': '👥'

    };

    return icones[materia] || '📖';

}

async function marcarConteudo(id, percentual) {

    await api('progresso', {
        method: 'POST',
        body: JSON.stringify({
            conteudo_id: id,
            percentual
        })
    });

    const materia = qs('materia-badge').textContent;

    todosConteudos = await api('conteudos');

    mostrarConteudosMateria(materia);

}

/* =========================
   SIMULADOS
========================= */


async function initSimulados() {

    const user = await requireUser();

    if (!user) return;

    header();

    const lista = qs('lista');

    lista.innerHTML = '<p class="muted">Carregando simulados...</p>';

    try {

        const dados = await api('simulados');

        if (!Array.isArray(dados) || dados.length === 0) {

            lista.innerHTML = `
                <div class="empty">
                    Nenhum simulado cadastrado no momento.
                </div>
            `;

            return;
        }

        lista.innerHTML = dados.map(simulado => `

            <article class="card">

                <span class="badge">
                    ${esc(simulado.materia)} • ${esc(simulado.nivel)}
                </span>

                <h3>${esc(simulado.titulo)}</h3>

                <p>${esc(simulado.descricao)}</p>

                <p>
                    📝 ${simulado.questoes} questões
                </p>

                <a
                    class="btn"
                    href="simulado.html?id=${simulado.id}">
                    Iniciar simulado
                </a>

            </article>

        `).join('');

    } catch (erro) {

        console.error('Erro ao carregar simulados:', erro);

        lista.innerHTML = `
            <div class="empty">
                Não foi possível carregar os simulados.
                Tente novamente.
            </div>
        `;

    }
}


let simuladoAtual = null;

async function initSimulado() {

    const user = await requireUser();

    if (!user) return;

    header();

    const id = new URLSearchParams(window.location.search).get('id');

    if (!id) {
        window.location.href = 'simulados.html';
        return;
    }

    try {

        simuladoAtual = await api('simulado&id=' + id);

        qs('titulo').textContent = simuladoAtual.titulo;

        qs('descricao').textContent =
            simuladoAtual.materia + ' • ' + simuladoAtual.nivel;

        qs('questoes').innerHTML = simuladoAtual.questoes.map(
            (questao, indice) => {

                const letras = ['a', 'b', 'c', 'd', 'e'];

                const alternativas = letras.map(letra => `
                    <label class="option">

                        <input
                            type="radio"
                            name="q${questao.id}"
                            value="${letra.toUpperCase()}"
                        >

                        ${letra.toUpperCase()})
                        ${esc(questao['alternativa_' + letra])}

                    </label>
                `).join('');

                return `
                    <div class="card question">

                        <b>Questão ${indice + 1}</b>

                        <p>${esc(questao.enunciado)}</p>

                        ${alternativas}

                    </div>
                `;

            }
        ).join('');

    } catch (erro) {

        console.error('Erro ao carregar simulado:', erro);

        alert('Não foi possível carregar o simulado.');

    }

}

async function finalizarSimulado() {

    if (!simuladoAtual) return;

    const respostas = {};

    simuladoAtual.questoes.forEach(questao => {

        const marcada = document.querySelector(
            `input[name="q${questao.id}"]:checked`
        );

        respostas[questao.id] = marcada ? marcada.value : null;

    });

    const dados = await api('finalizar_simulado', {

        method: 'POST',

        body: JSON.stringify({
            simulado_id: simuladoAtual.id,
            respostas
        })

    });

    window.location.href =
        'resultado.html?tentativa=' + dados.tentativa_id;

}

/* =========================
   RESULTADO
========================= */



async function initResultado() {

    const user = await requireUser();
    if (!user) return;

    header();

    const id = new URLSearchParams(
        window.location.search
    ).get('tentativa');

    const dados = await api(
        'gabarito&tentativa_id=' + id
    );

    qs('resultado').innerHTML = `
        <div class="card">

            <h2>${esc(dados.titulo)}</h2>

            <div class="stats">

                <div class="card">
                    <span>Acertos</span>
                    <strong>${dados.acertos}</strong>
                </div>

                <div class="card">
                    <span>Erros</span>
                    <strong>${dados.erros}</strong>
                </div>

                <div class="card">
                    <span>Aproveitamento</span>
                    <strong>${dados.percentual}%</strong>
                </div>

                <div class="card">
                    <span>XP</span>
                    <strong>+${dados.xp_ganho}</strong>
                </div>

            </div>

        </div>
    `;

    qs('gabarito').innerHTML = dados.questoes.map(
        (questao, indice) => {

            const acertou = Number(questao.correta) === 1;

            return `
                <article class="card questao-resultado">

                    <div class="questao-topo">

                        <b>Questão ${indice + 1}</b>

                        <span class="status-questao ${acertou ? 'correta' : 'errada'}">
                            ${acertou ? '✓ CORRETA' : '✕ ERRADA'}
                        </span>

                    </div>

                    <p>${esc(questao.enunciado)}</p>

                    <p>
                        Sua resposta:
                        <strong>
                            ${esc(questao.resposta || 'Não respondida')}
                        </strong>
                    </p>

                    <p>
                        Resposta correta:
                        <strong>
                            ${esc(questao.resposta_correta)}
                        </strong>
                    </p>

                    <p class="muted">
                        ${esc(questao.comentario)}
                    </p>

                </article>
            `;
        }
    ).join('');

}



/* =========================
   REDAÇÕES
========================= */


async function initRedacoes() {

    const user = await requireUser();
    if (!user) return;

    header();

    const temasContainer = document.getElementById("temas");
    const pendentesContainer = document.getElementById("redacoesPendentes");
    const corrigidasContainer = document.getElementById("redacoesCorrigidas");

    try {

        const [temas, minhas] = await Promise.all([
            api("temas_redacao"),
            api("minhas_redacoes")
        ]);

        // MOSTRAR TEMAS DISPONÍVEIS

        temasContainer.innerHTML = temas.map(tema => `
            <article class="card">

                <span class="badge">
                    Tema ${esc(tema.id)}
                </span>

                <h3>${esc(tema.titulo)}</h3>

                <p>${esc(tema.proposta)}</p>

                <p class="small muted">
                    ${esc(tema.orientacoes || "")}
                </p>

                <a class="btn"
                   href="redacao.html?id=${encodeURIComponent(tema.id)}">
                    Escrever
                </a>

            </article>
        `).join("");

        // SEPARAR REDAÇÕES PENDENTES E CORRIGIDAS
        // A correção só existe quando há nota registrada.

        const pendentes = minhas.filter(redacao =>
            redacao.nota === null || redacao.nota === undefined
        );

        const corrigidas = minhas.filter(redacao =>
            redacao.nota !== null && redacao.nota !== undefined
        );

        // FORMATAR DATA

        function formatarData(data) {

            if (!data) return "Data não informada";

            const partes = String(data).split(" ")[0].split("-");

            if (partes.length !== 3) return String(data);

            return `${partes[2]}/${partes[1]}/${partes[0]}`;
        }

        // CRIAR CARD DE REDAÇÃO

        function criarCardRedacao(redacao, corrigida) {

            const status = corrigida
                ? "Corrigida"
                : "Aguardando correção";

            return `
                <article class="redacao-historico-card">

                    <div class="redacao-historico-topo">

                        <span class="redacao-historico-status ${corrigida ? "status-corrigida" : "status-pendente"}">
                            ${corrigida ? "✅" : "⏳"} ${status}
                        </span>

                        <span class="redacao-historico-data">
                            📅 ${esc(formatarData(redacao.data_envio))}
                        </span>

                    </div>

                    <h3>${esc(redacao.tema || "Tema de redação")}</h3>

                    <details class="redacao-texto-detalhes">

                        <summary>
                            📄 Ver minha redação
                        </summary>

                        <div class="redacao-texto-salvo">
                            ${esc(redacao.texto || "Texto não disponível.")}
                        </div>

                    </details>

                    ${corrigida ? `

                        <div class="redacao-resultado">

                            <p class="redacao-nota">
                                🏆 Nota: ${esc(redacao.nota)} / 1000
                            </p>

                            <h4>Comentário do professor</h4>

                            <p>
                                ${esc(redacao.comentario || "Sem comentário.")}
                            </p>

                        </div>

                    ` : `

                        <p class="redacao-aguardando">
                            Sua redação foi enviada com sucesso e está
                            aguardando a avaliação de um professor.
                        </p>

                    `}

                </article>
            `;
        }

        // EXIBIR PENDENTES

        pendentesContainer.innerHTML = pendentes.length
            ? pendentes.map(redacao =>
                criarCardRedacao(redacao, false)
            ).join("")
            : `
                <div class="redacao-historico-vazio">
                    Nenhuma redação aguardando correção.
                </div>
            `;

        // EXIBIR CORRIGIDAS

        corrigidasContainer.innerHTML = corrigidas.length
            ? corrigidas.map(redacao =>
                criarCardRedacao(redacao, true)
            ).join("")
            : `
                <div class="redacao-historico-vazio">
                    Você ainda não possui redações corrigidas.
                </div>
            `;

    } catch (erro) {

        console.error("Erro ao carregar redações:", erro);

        const mensagem = esc(
            erro.message || "Não foi possível carregar suas redações."
        );

        if (pendentesContainer) {
            pendentesContainer.innerHTML =
                `<p class="muted">${mensagem}</p>`;
        }

        if (corrigidasContainer) {
            corrigidasContainer.innerHTML = "";
        }

    }

}


async function initRedacao() {

    const user = await requireUser();
    if (!user) return;

    header();

    const id = new URLSearchParams(window.location.search).get('id');

    const temas = await api('temas_redacao');

    const temaEncontrado = temas.find(
        tema => String(tema.id) === String(id)
    );

    if (!temaEncontrado) {
        window.location.href = 'redacoes.html';
        return;
    }

    qs('tema').textContent = temaEncontrado.titulo;
    qs('proposta').textContent = temaEncontrado.proposta;

    qs('orientacoes').textContent =
        temaEncontrado.orientacoes || '';

    qs('enviar').onclick = async function () {

        try {

            await api('enviar_redacao', {

                method: 'POST',

                body: JSON.stringify({
                    tema_id: id,
                    texto: qs('texto').value
                })

            });

            alert('Redação enviada para correção!');

            window.location.href = 'redacoes.html';

        } catch (erro) {

            setMsg('msg', erro.message);

        }

    };

}

/* =========================
   RANKING
========================= */

async function initRanking() {

    const user = await requireUser();
    if (!user) return;

    header();

    const dados = await api('ranking');

    qs('lista').innerHTML = `
        <table class="table">

            <thead>
                <tr>
                    <th>#</th>
                    <th>Aluno</th>
                    <th>XP</th>
                    <th>Nível</th>
                </tr>
            </thead>

            <tbody>

                ${dados.map(aluno => `
                    <tr>

                        <td>${esc(aluno.posicao)}º</td>

                        <td>${esc(aluno.nome)}</td>

                        <td>${esc(aluno.xp)}</td>

                        <td>${esc(aluno.nivel)}</td>

                    </tr>
                `).join('')}

            </tbody>

        </table>
    `;

}

/* =========================
   PLANOS
========================= */

let planosDisponiveis = [];

async function initPlanos() {

    const user = await requireUser();
    if (!user) return;

    header();

    const lista = qs('lista');

    if (!lista) {
        console.error('Elemento #lista não encontrado.');
        return;
    }

    try {

        const dados = await api('planos');

        planosDisponiveis = Array.isArray(dados) ? dados : [];

        if (planosDisponiveis.length === 0) {

            lista.innerHTML = `
                <div class="empty">
                    Nenhum plano disponível no momento.
                </div>
            `;

            return;
        }

        const ordem = {
            gratuito: 0,
            mensal: 1,
            semestral: 2,
            anual: 3
        };

        planosDisponiveis.sort((a, b) => {

            const nomeA = String(a.nome || '').toLowerCase();
            const nomeB = String(b.nome || '').toLowerCase();

            return (ordem[nomeA] ?? 99) -
                   (ordem[nomeB] ?? 99);

        });

        lista.innerHTML = planosDisponiveis.map(plano => {

            const valor = Number(plano.valor_mensal);

            if (!Number.isFinite(valor)) return '';

            const valorFormatado = valor.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            const nomePlano =
                String(plano.nome || '').toLowerCase();

            const destaque = nomePlano === 'semestral'
                ? ' destaque'
                : '';

            const gratuito = nomePlano === 'gratuito'
                ? ' gratuito'
                : '';

            const duracao =
                Number(plano.periodo_meses) || 1;

            return `
                <article class="card-plano${destaque}${gratuito}">

                    <h2>${esc(plano.nome)}</h2>

                    <div class="preco">

                        R$ ${valorFormatado}

                        ${valor === 0
                            ? '<small>grátis</small>'
                            : '<small>/mês</small>'
                        }

                    </div>

                    <p class="descricao-plano">
                        ${esc(plano.descricao || '')}
                    </p>

                    <p class="duracao">

                        ${valor === 0
                            ? 'Acesso gratuito'
                            : `${duracao} ${duracao === 1 ? 'mês' : 'meses'}`
                        }

                    </p>

                    <button
                        class="btn-escolher"
                        type="button"
                        data-plano-id="${esc(String(plano.id))}"
                    >

                        ${valor === 0
                            ? 'Começar grátis'
                            : 'Escolher plano'
                        }

                    </button>

                </article>
            `;

        }).join('');

        lista.querySelectorAll('.btn-escolher').forEach(botao => {

            botao.addEventListener('click', function () {

                escolherPlano(this.dataset.planoId);

            });

        });

    } catch (erro) {

        console.error('Erro ao carregar os planos:', erro);

        lista.innerHTML = `
            <div class="empty">
                Não foi possível carregar os planos.
                Verifique a conexão com o servidor.
            </div>
        `;

        setMsg(
            'mensagemPlanos',
            'Ocorreu um erro ao carregar os planos.'
        );

    }

}

//escolher plano
async function escolherPlano(id) {

    const plano = planosDisponiveis.find(
        p => String(p.id) === String(id)
    );

    if (!plano) {
        alert('Plano não encontrado.');
        return;
    }

    // Se for gratuito, ativa diretamente
    if (Number(plano.valor_mensal) === 0) {

        try {

            await api('assinar', {
                method: 'POST',
                body: JSON.stringify({
                    plano_id: Number(plano.id),
                    forma_pagamento: 'Gratuito'
                })
            });

            window.location.href = 'boas-vindas.html';

        } catch (erro) {

            console.error(erro);
            alert(erro.message || 'Não foi possível ativar o plano gratuito.');

        }

        return;
    }

    // Se for pago, continua para a confirmação
    sessionStorage.setItem(
        'planoEscolhido',
        JSON.stringify({
            id: plano.id,
            nome: plano.nome,
            valor_mensal: plano.valor_mensal,
            descricao: plano.descricao || '',
            periodo_meses: plano.periodo_meses
        })
    );

    window.location.href = 'confirmar-plano.html';
}


/*
 * Contrata somente depois do clique em Finalizar.
 */

async function assinar(id) {

    const botao = qs('finalizar');

    if (botao) {
        botao.disabled = true;
        botao.textContent = 'Finalizando...';
    }

    try {

        const formaPagamento =
            qs('formaPagamento')?.value || 'Pix';

        const dados = await api('assinar', {

            method: 'POST',

            body: JSON.stringify({
                plano_id: Number(id),
                forma_pagamento: formaPagamento
            })

        });

        sessionStorage.removeItem('planoEscolhido');

        alert(dados.mensagem || 'Plano contratado com sucesso!');

        window.location.href = 'dashboard.html';

    } catch (erro) {

        console.error(erro);

        alert(
            erro.message ||
            'Não foi possível finalizar a contratação.'
        );

        if (botao) {
            botao.disabled = false;
            botao.textContent = 'Finalizar';
        }

    }

}

/* =========================
   PERFIL
========================= */

async function initPerfil() {

    const user = await requireUser();
    if (!user) return;

    header();

    const dados = await api('perfil');

    qs('perfil').innerHTML = `
        <div class="card">

            <h2>${esc(dados.nome)}</h2>

            <p>
                <b>E-mail:</b>
                ${esc(dados.email)}
            </p>

            <p>
                <b>Objetivo:</b>
                ${esc(dados.objetivo || 'Não informado')}
            </p>

            <p>
                <b>Tipo:</b>
                ${esc(dados.tipo)}
            </p>

            <p>
                <b>XP:</b>
                ${esc(dados.xp)}
            </p>

            <p>
                <b>Nível:</b>
                ${esc(dados.nivel)}
            </p>

            <p>
                <b>Plano:</b>
                ${esc(dados.plano || 'Gratuito')}
            </p>

        </div>
    `;

}

/* =========================
   CALENDÁRIO
========================= */

async function initCalendario() {

    // Verifica se o usuário possui plano pago
    const permitido = await requirePaidPlan();

    if (!permitido) {
        return;
    }

    const user = await requireUser();
    if (!user) return;

    header();

    const tarefas = await api('tarefas');

    if (tarefas.length > 0) {

        qs('tarefas').innerHTML = tarefas.map(tarefa => `
            <div class="card">

                <b>${esc(tarefa.titulo)}</b>

                <p class="small">
                    ${esc(tarefa.data)}
                    ${tarefa.horario ? esc(tarefa.horario) : ''}
                </p>

                <span class="badge">
                    ${esc(tarefa.tipo)} • ${esc(tarefa.status)}
                </span>

            </div>
        `).join('');

    } else {

        qs('tarefas').innerHTML =
            '<div class="empty">Nenhuma atividade. Use o formulário para criar.</div>';

    }

    qs('salvar').onclick = async function () {

        await api('tarefas', {

            method: 'POST',

            body: JSON.stringify({

                titulo: qs('titulo').value,
                descricao: qs('descricao').value,
                data: qs('data').value,
                horario: qs('horario').value,
                duracao: qs('duracao').value,
                tipo: qs('tipo').value

            })

        });

        window.location.reload();

    };

}

/* =========================
   PROFESSOR
========================= */

async function initProfessor() {
    

    const user = await requireUser();
    if (!user) return;
    // MOSTRAR NOME DO PROFESSOR LOGADO
const boasVindas = document.getElementById("boasVindasProfessor");

if (boasVindas) {
    const nomeProfessor = String(user.nome || "Professor").trim();

    boasVindas.textContent = `Bem-vindo(a), ${nomeProfessor}! 💜`;
}

    

if (user.tipo !== 'professor') {
    window.location.replace('login-professor.html');
    return;
}



    header();

    const dados = await api('redacoes_professor');
    
const redacoesPendentes = dados.filter(redacao => {
    return redacao.status !== 'corrigida'
        && redacao.nota == null;
});


    if (redacoesPendentes.length > 0) {

        qs('lista').innerHTML = redacoesPendentes.map(redacao => `
            <article class="card">

                <span class="badge">
                    ${esc(redacao.status)}
                </span>

                <h3>${esc(redacao.tema)}</h3>

                <p>
                    <b>Aluno:</b>
                    ${esc(redacao.aluno)}
                </p>

                <p class="small">
                    ${esc(redacao.texto)}
                </p>

                <div class="two">

                    <div class="field">

                        <label for="nota${esc(redacao.id)}">
                            Nota
                        </label>

                        <input
                            id="nota${esc(redacao.id)}"
                            type="number"
                            min="0"
                            max="1000"
                            value="${redacao.nota === null ? '' : esc(redacao.nota)}"
                        >

                    </div>

                    <div class="field">

                        <label for="coment${esc(redacao.id)}">
                            Comentário
                        </label>

                        <textarea
                            id="coment${esc(redacao.id)}"
                            style="min-height:120px"
                        >${esc(redacao.comentario || '')}</textarea>

                    </div>

                </div>

                <button
                    class="btn green"
                    onclick="corrigir(${Number(redacao.id)})"
                >
                    Salvar correção
                </button>

            </article>
        `).join('');

    } else {

        qs('lista').innerHTML =
            '<div class="empty">Nenhuma redação enviada.</div>';

    }
    
    await carregarHistoricoProfessor();

}


/* =========================
   CORREÇÃO DE REDAÇÕES
========================= */

async function corrigir(id) {

    await api('corrigir_redacao', {
        method: 'POST',
        body: JSON.stringify({
            redacao_id: id,
            nota: qs('nota' + id).value,
            comentario: qs('coment' + id).value
        })
    });

    alert('Correção salva!');
    window.location.reload();
}


/* =========================
   MASCOTE POR NÍVEL
========================= */

function atualizarMascoteNivel(nivel) {

    const numeroNivel = Number(nivel);

    const nivelValido =
        Number.isFinite(numeroNivel) && numeroNivel >= 1
            ? numeroNivel
            : 1;

    let imagem = 'img/nivel1.png';

    if (nivelValido === 2) {
        imagem = 'img/nivel2.png';
    } else if (nivelValido >= 3) {
        imagem = 'img/nivel3.png';
    }

    document.querySelectorAll(
        '#mascoteNivel, .mascote-por-nivel'
    ).forEach(mascote => {

        if (mascote.tagName !== 'IMG') return;

        mascote.src = imagem;

        mascote.alt =
            'Mascote BRIO - Nível ' + nivelValido;

    });

}


/* =========================
   CARREGAR MASCOTE DO PROGRESSO
========================= */

async function carregarMascoteProgresso() {

    const mascotes = document.querySelectorAll(
        '#mascoteNivel, .mascote-por-nivel'
    );

    if (mascotes.length === 0) return;

    if (!window.location.pathname.endsWith('progresso.html')) {
        return;
    }

    try {

        const usuario = await requireUser();

        if (!usuario) return;

        // Consulta o nível real no banco de dados
        const dados = await api('perfil');

        // Atualiza as imagens automaticamente
        atualizarMascoteNivel(dados.nivel);

    } catch (erro) {

        console.error(
            'Erro ao carregar mascote:',
            erro
        );

    }

}


/* =========================
   INICIALIZAÇÃO AUTOMÁTICA
========================= */

document.addEventListener(
    'DOMContentLoaded',
    carregarMascoteProgresso
);

// =====================================
// MOSTRAR ATIVIDADES NO DASHBOARD
// =====================================


/* =====================================
   BRIO - ATIVIDADES NO DASHBOARD
===================================== */

async function carregarRotinaDashboard() {

    const container = document.getElementById("rotina");

    if (!container) return;

    container.innerHTML = "<p>Carregando atividades...</p>";

    try {

        // O PHP já retorna as tarefas nesta ação.
        const dados = await api("dashboard");

        const atividades = Array.isArray(dados.tarefas)
            ? dados.tarefas
            : [];

        if (atividades.length === 0) {

            container.innerHTML = `
                <div class="rotina-vazia">
                    <span>🗓️</span>
                    <p>Nenhuma atividade cadastrada.</p>
                </div>
            `;

            return;
        }

        container.innerHTML = atividades.map(atividade => {

            const dataFormatada = atividade.data
                ? String(atividade.data)
                    .slice(0, 10)
                    .split("-")
                    .reverse()
                    .join("/")
                : "Sem data";

            const horario = atividade.horario
                ? String(atividade.horario).slice(0, 5)
                : "";

            const tipo = {
                estudo: "📚",
                simulado: "📝",
                redacao: "✍️",
                outro: "📌"
            }[atividade.tipo] || "📚";

            const concluida = atividade.status === "concluida";

            return `
                <div class="rotina-atividade">

                    <div class="rotina-atividade-icone">
                        ${tipo}
                    </div>

                    <div class="rotina-atividade-info">

                        <h3>${esc(atividade.titulo || "Atividade")}</h3>

                        <p>
                            📅 ${esc(dataFormatada)}
                            ${horario ? ` • 🕒 ${esc(horario)}` : ""}
                        </p>

                        <small>
                            ${concluida
                                ? "✅ Concluída"
                                : "⏳ Pendente"}
                        </small>

                    </div>

                </div>
            `;

        }).join("");

    } catch (erro) {

        console.error("Erro ao carregar rotina:", erro);

        container.textContent =
            "Não foi possível carregar suas atividades.";

    }

}

/* =========================================
   ATUALIZAR ESTATÍSTICAS DO DASHBOARD
========================================= */

async function atualizarContadoresDashboard() {

    try {

        // Busca as informações do aluno no banco
        const dados = await api("dashboard");

        // Atualiza o XP
        const xp = document.getElementById("xpAluno");

        if (xp) {
            xp.textContent = dados.xp ?? 0;
        }

        // Atualiza o nível
        const nivel = document.getElementById("nivelAluno");

        if (nivel) {
            nivel.textContent = dados.nivel ?? 1;
        }

        // Atualiza a quantidade de simulados
        const simulados = document.getElementById("totalSimulados");

        if (simulados) {
            simulados.textContent = dados.simulados ?? 0;
        }

        // Atualiza a quantidade de redações
        const redacoes = document.getElementById("totalRedacoes");

        if (redacoes) {
            redacoes.textContent = dados.redacoes ?? 0;
        }

    } catch (erro) {

        console.error(
            "Erro ao carregar estatísticas:",
            erro
        );

    }

}

/* =========================================
   HISTÓRICO DE CORREÇÕES DO PROFESSOR
========================================= */

async function carregarHistoricoProfessor() {

    const container = document.getElementById(
        "historicoCorrecoesProfessor"
    );

    const contador = document.getElementById(
        "totalCorrecoesProfessor"
    );

    if (!container) return;

    try {

        const redacoes = await api(
            "minhas_correcoes_professor"
        );

        if (contador) {
            contador.textContent = redacoes.length;
        }

        if (redacoes.length === 0) {

            container.innerHTML = `
                <div class="professor-historico-vazio">
                    <span>📚</span>
                    <h3>Seu histórico começa aqui!</h3>
                    <p>
                        Quando você corrigir sua primeira
                        redação, ela aparecerá nesta seção.
                    </p>
                </div>
            `;

            return;
        }

        container.innerHTML = redacoes.map(redacao => {

            const data = redacao.data_correcao
                ? new Date(
                    String(redacao.data_correcao)
                        .replace(" ", "T")
                  ).toLocaleDateString("pt-BR")
                : "Data não disponível";

            return `
                <article class="professor-historico-card">

                    <div class="professor-historico-card-topo">

                        <span class="professor-corrigida-badge">
                            ✓ Corrigida
                        </span>

                        <span class="professor-data-correcao">
                            📅 ${esc(data)}
                        </span>

                    </div>

                    <h3>${esc(redacao.tema)}</h3>

                    <p class="professor-historico-aluno">
                        👤 <strong>Aluno:</strong>
                        ${esc(redacao.aluno)}
                    </p>

                    <div class="professor-nota-box">
                        <span>Nota atribuída</span>
                        <strong>
                            ${esc(redacao.nota)} / 1000
                        </strong>
                    </div>

                    <div class="professor-comentario-box">
                        <strong>💬 Seu comentário</strong>
                        <p>${esc(redacao.comentario)}</p>
                    </div>

                    <details class="professor-redacao-detalhes">

                        <summary>
                            📖 Visualizar redação do aluno
                        </summary>

                        <div class="professor-redacao-texto">
                            ${esc(redacao.texto)}
                        </div>

                    </details>

                </article>
            `;

        }).join("");

    } catch (erro) {

        console.error(
            "Erro ao carregar histórico:",
            erro
        );

        container.innerHTML = `
            <div class="professor-historico-vazio">
                <p>
                    Não foi possível carregar
                    suas correções.
                </p>
            </div>
        `;

    }
}

