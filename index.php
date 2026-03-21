<?php
session_start();
require_once('Connections/conexao.php');

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Viação Andrade</title>
  <link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />

  <!-- Bootstrap Core CSS -->
  <link rel="stylesheet" href="css/bootstrap.css" type="text/css"> 
  <!-- Custom Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Merriweather:300,400,700,900" rel="stylesheet">
  <link rel="stylesheet" href="font-awesome/css/font-awesome.min.css" type="text/css">
  <link rel="stylesheet" type="text/css" href="css/ekko-lightbox.css">
  <link rel="stylesheet" href="css/animate.min.css" type="text/css">
  <link rel="stylesheet" href="css/style.css" type="text/css">

  <?php
  // Gera imagem aleatória automaticamente de uma pasta
  $imagens = glob("img/foto*.jpg");
  $imagemEscolhida = $imagens[array_rand($imagens)];
?>

<style>
  header {
    background-image: url(<?php echo $imagemEscolhida; ?>);
  }
    .height-100 {
      height: 100vh;
    }
  </style>

    <!-- JS principais via CDN -->
  <script src="https://code.jquery.com/jquery-3.6.4.min.js" integrity="sha256-oP6HI9z1XaZNBrJURtCoUT5SUnxFr8s3BzRl+cbzUq8=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js" integrity="sha512-n7L2B6Fz0DrhO0UyP6eF2USytzszbmWzxubUANe0yoyS9MhUlCT3VkOITkkpFmgnTTyt7urYJKicN7P5yBtKeg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-easing/1.4.1/jquery.easing.min.js" integrity="sha512-0xCM4fOtNvDvZC2CUZwTe74huDgCWjaHQRSP2YV52S/bR1WBi3Jj7giZb6xmR8CRF7kxz8Ksglc+huxPwC/aRQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/wow/1.1.2/wow.min.js" integrity="sha512-b2c3UGpzIi9n24AyE6ZCgjyrdvuSW6D1ivu4ylMZu4rwslcqM6T6nYbFh5896T4NlupJbdap9E/6VUGbTbYqlA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/ekko-lightbox/5.3.0/ekko-lightbox.min.js" integrity="sha512-K1n3h7GK8lpNoGsAHdG1szANkemoUMCfbpyc7Xyv0BmbfMSDcPchRdwq+SoqH3pX2Z3HFqRSbuuAjAI+Hjci7w==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
  $(document).ready(function() {
    // Verifica se o jQuery e o ekkoLightbox estão carregados
    if (typeof $ === 'undefined' || typeof $.fn.ekkoLightbox === 'undefined') {
      console.error("jQuery ou ekkoLightbox não carregado corretamente.");
      return;
    }

    // Lightbox
    $(document).on('click', '*[data-toggle="lightbox"]:not([data-gallery="navigateTo"])', function(event) {
      event.preventDefault();
      $(this).ekkoLightbox({
        onShown: function() {
          if (window.console) console.log('onShown event fired');
        },
        onContentLoaded: function() {
          if (window.console) console.log('onContentLoaded event fired');
        },
        onNavigate: function(direction, itemIndex) {
          if (window.console) console.log('Navigating ' + direction + '. Current item: ' + itemIndex);
        }
      });
    });
  });
</script>

</head>

<body id="page-top">
    <nav id="mainNav" class="navbar navbar-default navbar-fixed-top">
        <div class="container-fluid">
            <!-- Brand para dispositivo mobile -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                        
                <ul class="nav navbar-nav">
                    <li>
                        <a class="navbar-brand page-scroll" href="#page-top">Home</a>               
                    </li>
                </ul>
            </div>

            <!-- Collect the nav links, forms, and other content for toggling -->
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav navbar-right">
                    <li><a class="page-scroll" href="#sobre">Sobre</a></li>
                    <li><a class="page-scroll" href="#iso">ISO</a></li>
                    <li><a class="page-scroll" href="#clientes">Clientes</a></li>
                    <li><a class="page-scroll" href="#localizacao">Localização</a></li>
                    <li><a class="page-scroll" href="#contatos">Contatos</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header>
        <div class="header-content">
            <div class="header-content-inner">
                <h1><img src="img/logo.png" width="351" height="207" alt=""></h1>
                <hr>
                <p>Transportando com conforto e segurança</p>
                <a href="#sobre" class="btn btn-primary btn-xl page-scroll">Mais Sobre Nós</a>
            </div>
        </div>
    </header>

    <section class="bg-primary" id="sobre" class="height-100">
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 text-center">
                    <h2 class="section-heading">Viação Andrade Ltda</h2>
                    <hr class="light">
                    <p class="text-faded">
                    <p class="text-faded"> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Sua origem está ligada a linha Araxá/Barreiro adquirida da antiga Empresa Java no ano de 1968. Seu primeiro sócio/diretor foi Lourival Pereira de Andrade, o saudoso “Lourinho” que juntamente com seus irmãos fundaram a Viação Andrade.
                        Com a presença das mineradoras no final da década de 60 e início dos anos 70, a empresa começa a investir em fretamento aumentando gradativamente os investimentos neste segmento. Em 1982 com o falecimento de seu fundador, a Andrade com nova direção procura desenvolver a atividade de turismo.<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;A empresa consolida sua posição no mercado há mais de 50 anos atuando no ramo de transporte de passageiros, acumulando experiências, conta com uma equipe de funcionários que constantemente passa por treinamentos nas áreas específicas de atuação. Tem uma frota moderna e eficiente, procurando atender da melhor forma seus usuários, transportando com pontualidade, conforto e segurança.<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Seus diretores e funcionários agradecem a todos seus clientes pela preferência mantendo sua consciência tranqüila por desenvolver um trabalho sério e digno naquilo que se propôs ao longo de sua história: transportar com “precisão e segurança” a nossa gente que trabalha, se diverte e estuda.<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Procurando cada vez mais o aperfeiçoamento e modernização, a Andrade implantou o Sistema de Gestão Integrado e o têm reconhecido através das certificações ISO 9001:2015, ISO 14001:2015 e ISO 45001:2018 firmando sua preocupação constante com a qualidade dos serviços prestados, na preservação do Meio Ambiente e na preocupação com a Saúde e Segurança do Trabalho.</p>
                    </p>
                </div>
            </div>
        </div>
    </section>

            <section id="iso" class="iso-cards">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-12">
                    <p class="clientes-eyebrow">Certificações</p>
                    <h2 class="section-heading">ISO & Gestão Integrada</h2>
                    <p class="clientes-lead">Compromisso com qualidade, meio ambiente e segurança.</p>
                </div>
            </div>

            <div class="iso-grid">
                <a href="img/Cert9001.jpg" data-toggle="lightbox" class="iso-card">
                    <div class="iso-icon"><i class="fa fa-certificate"></i></div>
                    <h3>ISO 9001</h3>
                    <p class="iso-link">ISO da Qualidade</p>
                </a>
                <a href="img/Cert14001.jpg" data-toggle="lightbox" class="iso-card">
                    <div class="iso-icon"><i class="fa fa-leaf"></i></div>
                    <h3>ISO 14001</h3>
                    <p class="iso-link">ISO Meio Ambiente</p>
                </a>
                <a href="img/Cert18001.jpg" data-toggle="lightbox" class="iso-card">
                    <div class="iso-icon"><i class="fa fa-shield"></i></div>
                    <h3>ISO 45001</h3>
                    <p class="iso-link">ISO Segurança do Trabalho</p>
                </a>
            </div>
        </div>
    </section>

        <section id="clientes" class="clientes">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-12">
                    <p class="clientes-eyebrow">Parcerias</p>
                    <h2 class="section-heading">Alguns de nossos Clientes</h2>
                    <p class="clientes-lead">Marcas que confiam no nosso transporte diário.</p>
                </div>
            </div>

            <div class="clientes-logos">
                <a href="http://www.cbmm.com.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/cbmm.jpg" alt="CBMM">
                </a>
                <a href="https://www.mccain.com.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/mccain.jpg" alt="McCain">
                </a>
                <a href="http://www.comipa.com.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/comipa.jpg" alt="Comipa">
                </a>
                <a href="https://www.bembrasil.ind.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/bembrasil.jpg" alt="Bem Brasil">
                </a>
                <a href="http://www.mroscoe.com.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/mroscoe.jpg" alt="M. Roscoe">
                </a>
                <a href="https://empresasfuncional.com.br/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/funcional.jpg" alt="Funcional">
                </a>
                <a href="http://grupoayres.com/" target="_blank" class="cliente-card">
                    <img src="img/portfolio/ayres.jpg" alt="Grupo Ayres">
                </a>
            </div>
        </div>
    </section>

    <section id="localizacao">
        <div class="container">  
            <div class="col-md-12 text-center">
                <h2 class="section-heading">Localização</h2>
                <hr class="primary">
                <div class="col-md-12 text-center">
                    <iframe
                        src="https://www.google.com/maps?q=Viacao+Andrade+Arax%C3%A1+MG&output=embed"
                        width="100%"
                        height="420"
                        style="border:0;"
                        allowfullscreen
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                    <p style="margin-top:15px;">
                        <a class="btn btn-default btn-xl" target="_blank" rel="noopener"
                           href="https://www.google.com/maps?q=Viacao+Andrade+Arax%C3%A1+MG">
                            Ver mapa ampliado
                        </a>
                    </p>
                </div>
            </div>
        </div>   
    </section>

    <section id="contatos" class="content-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-12">
                <h2 class="section-heading">Contatos</h2>
                <hr class="primary">
                <p>Entre em contato conosco pelo formulário abaixo ou através dos canais diretos:</p>
                <p><strong>Email:</strong> comercial@viacaoandrade.com.br</p>
                <p><strong>Telefone:</strong> (34) 3691-3200</p>
            </div>
        </div>

        <div class="well">
<?php if (isset($_GET['contato'])): ?>
    <?php if ($_GET['contato'] === 'sucesso'): ?>
        <div class="alert alert-success" role="alert" style="margin-top:15px;">
            Mensagem enviada com sucesso. Obrigado.
        </div>
    <?php elseif ($_GET['contato'] === 'erro'): ?>
        <div class="alert alert-danger" role="alert" style="margin-top:15px;">
            Não foi possível enviar a mensagem agora. Tente novamente.
        </div>
    <?php endif; ?>
<?php endif; ?>
            <form data-toggle="validator" role="form" action="enviar.php" method="POST" name="form-contato" id="form-contato">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="nome">Nome</label>
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Informe seu nome" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone" placeholder="Informe seu telefone" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Informe seu e-mail" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cidade">Cidade</label>
                            <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Informe sua cidade" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="estado">Estado</label>
                            <select class="form-control" id="estado" name="estado" required>
                                    <option value="">Escolha...</option>
                                    <option value="AC">Acre</option>
                                    <option value="AL">Alagoas</option>
                                    <option value="AP">Amapá</option>
                                    <option value="AM">Amazonas</option>
                                    <option value="BA">Bahia</option>
                                    <option value="CE">Ceará</option>
                                    <option value="DF">Distrito Federal</option>
                                    <option value="ES">Espirito Santo</option>
                                    <option value="GO">Goiás</option>
                                    <option value="MA">Maranhão</option>
                                    <option value="MT">Mato Grosso</option>
                                    <option value="MS">Mato Grosso do Sul</option>
                                    <option value="MG">Minas Gerais</option>
                                    <option value="PA">Pará</option>
                                    <option value="PB">Paraiba</option>
                                    <option value="PR">Paraná</option>
                                    <option value="PE">Pernambuco</option>
                                    <option value="PI">Piauí</option>
                                    <option value="RJ">Rio de Janeiro</option>
                                    <option value="RN">Rio Grande do Norte</option>
                                    <option value="RS">Rio Grande do Sul</option>
                                    <option value="RO">Rondônia</option>
                                    <option value="RR">Roraima</option>
                                    <option value="SC">Santa Catarina</option>
                                    <option value="SP">São Paulo</option>
                                    <option value="SE">Sergipe</option>
                                    <option value="TO">Tocantis</option>   
                                <!-- Adicione outros estados conforme necessário -->
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="mensagem">Tipo de Mensagem</label>
                            <select class="form-control" id="mensagem" name="mensagem" required>
                                <option value="">Escolha...</option>
                                <option value="Sugestões">Sugestões</option>
                                <option value="Reclamações">Reclamações</option>
                                <option value="Elogios">Elogios</option>
                                <option value="Denuncias">Denúncias</option>
                                <option value="Orçamento">Orçamento</option>
                                <option value="Contato">Contato</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email_site">Setor</label>
                            <select class="form-control" id="email_site" name="email_site" required>
                                <option value="">Escolha...</option>
                                <option value="administrativo@viacaoandrade.com.br">Administrativo</option>
                                <option value="departamentopessoal@viacaoandrade.com.br">Departamento Pessoal</option>
                                <option value="financeiro@viacaoandrade.com.br">Financeiro</option>
                                <option value="gestaoadministrativa@viacaoandrade.com.br">Gerência Gestão</option>
                                <option value="manutencao@viacaoandrade.com.br">Manutenção</option>
                                <option value="recursoshumanos@viacaoandrade.com.br">Recursos Humanos</option>
                                <option value="seguranca@viacaoandrade.com.br">Segurança do Trabalho</option>
                                <option value="sistemagestao@viacaoandrade.com.br">SGI</option>
                                <option value="transporte@viacaoandrade.com.br">Transporte</option>
                                <option value="turismo@viacaoandrade.com.br">Turismo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="texto">Mensagem</label>
                    <textarea class="form-control" id="texto" name="texto" rows="4" required></textarea>
                </div>
                    <input type="hidden" name="token" value="<?php echo $_SESSION['token'] = $token = hash('md5', rand().time().rand()); ?>">
                    <input type="hidden" name="MM_contato" value="contato">

                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">Enviar Mensagem</button>
                        <button type="reset" class="btn btn-secondary">Limpar</button>
                </div>
            </form>
        </div>
    </div>
</section>



<!-- footer -->
<aside class="bg-dark">
    <footer>
        <div class="container-fluid">
            
                <p>Viação Andrade Ltda - Copyright &copy; - Todos os direitos reservados</p>
              <div class="restrito">
                <strong><a href="#/"><img src="img/acesso_restrito.png" width="100" height="55" alt=""/></a></strong>
                 </div>
              <div class="logoag">
                <!--<strong><a href="http://www.agenciaclic.com.br/"><img src="img/clic.png" width="44" height="38" alt=""/></a></strong>-->
            </div>
        </div>
         
       
    </footer>
<!-- fim - footer -->
</aside>
<script>
(function() {
  var toggle = document.querySelector('.navbar-toggle');
  var target = document.getElementById('bs-example-navbar-collapse-1');
  if (!toggle || !target) return;

  toggle.addEventListener('click', function (ev) {
    ev.preventDefault();
    var isOpen = target.classList.contains('in');
    target.classList.toggle('in', !isOpen);
    toggle.classList.toggle('collapsed', isOpen);
    toggle.setAttribute('aria-expanded', String(!isOpen));
  });

  target.addEventListener('click', function (ev) {
    if (ev.target.tagName === 'A' && target.classList.contains('in')) {
      target.classList.remove('in');
      toggle.classList.add('collapsed');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
})();
</script>
<script>
(function ($) {
  function getNavOffset() {
    var $nav = $('#mainNav');
    var brandHeight = $nav.find('.navbar-header').outerHeight() || 0;
    var navHeight = $nav.outerHeight() || 0;
    var offset = brandHeight || navHeight;
    return offset || 0;
  }

  // Evita que o hash (#clientes etc.) fique na URL ao navegar pelo menu
  $('.page-scroll').on('click', function (ev) {
    var hash = this.hash;
    if (!hash) return;
    var $target = $(hash);
    if (!$target.length) return;

    ev.preventDefault();
    var navOffset = getNavOffset();
    var targetTop = Math.max($target.offset().top - navOffset, 0);
    $('html, body')
      .stop()
      .animate(
        { scrollTop: targetTop },
        600,
        'swing',
        function () {
          if (history.replaceState) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
          }
        }
      );
  });

  // Limpa hash se a pǭgina for carregada jǭ com ele
  if (window.location.hash && history.replaceState) {
    history.replaceState(null, '', window.location.pathname + window.location.search);
  }
})(jQuery);
</script></body>
</html>






