/**
 * Terms of Service page.
 */

import { BookOpen } from 'lucide-react';

export function TermsPage() {
  return (
    <div className="max-w-4xl mx-auto">
      <div className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          <div className="w-12 h-12 bg-primary rounded-lg flex items-center justify-center shadow-lg">
            <BookOpen className="w-7 h-7 text-white" />
          </div>
          <div>
            <h1 className="text-3xl font-bold text-foreground">Condiciones del Servicio</h1>
            <p className="text-muted-foreground">Última actualización: Febrero 2026</p>
          </div>
        </div>
      </div>

      <div className="bg-card rounded-xl shadow-sm border border-border p-8 space-y-8 prose prose-slate dark:prose-invert max-w-none">
        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">1. Titular del sitio</h2>
          <p className="text-muted-foreground">
            El titular del sitio <strong>lexemas.com</strong> es <strong>SIMPLICER, S.L.</strong>, con domicilio en
            Av. de la Libertad, 37, 04639, Turre, Almería, España. Este sitio no es la UNED ni está afiliado a dicha institución.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">2. Objeto del servicio</h2>
          <p className="text-muted-foreground">
            Este sitio ofrece un servicio informativo de agregación y consulta de actividades de extensión universitaria.
            La información se proporciona exclusivamente con fines informativos.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">3. Origen y fiabilidad de los datos</h2>
          <p className="text-muted-foreground">
            Los datos se obtienen de la web pública de la UNED, en particular de <a className="text-primary hover:underline" href="https://extension.uned.es" target="_blank" rel="noopener noreferrer">extension.uned.es</a>.
            La información puede contener errores o estar desactualizada, por lo que el usuario debe verificarla en la actividad original.
            El sitio proporciona un enlace directo a la página de la UNED correspondiente.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">4. Aceptación</h2>
          <p className="text-muted-foreground">
            El acceso y uso del sitio implica la aceptación plena de estas condiciones. Si no está de acuerdo, debe abstenerse
            de utilizar el servicio.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">5. Exclusión de responsabilidad</h2>
          <p className="text-muted-foreground">
            El servicio se ofrece “tal cual”, sin garantías de ningún tipo. SIMPLICER, S.L. no garantiza la exactitud,
            disponibilidad o actualidad de los datos, ni responde por daños directos o indirectos derivados del uso de este sitio.
            El usuario acepta que el uso de la información se realiza bajo su exclusiva responsabilidad.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">6. Enlaces a terceros</h2>
          <p className="text-muted-foreground">
            Este sitio enlaza a páginas de terceros (UNED). SIMPLICER, S.L. no controla ni responde por dichos contenidos
            o por las políticas de esos sitios.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">7. Propiedad intelectual</h2>
          <p className="text-muted-foreground">
            El software se distribuye bajo licencia MIT. Los contenidos y marcas de la UNED pertenecen a dicha institución.
            SIMPLICER, S.L. no reclama derechos sobre los contenidos de terceros.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">8. Cumplimiento legal</h2>
          <p className="text-muted-foreground">
            Este sitio cumple con la normativa española y europea aplicable, incluyendo la LSSI-CE, el RGPD y la LOPDGDD.
            No se realizan actividades de comercio electrónico.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">9. Modificaciones</h2>
          <p className="text-muted-foreground">
            SIMPLICER, S.L. podrá modificar estas condiciones en cualquier momento. Las modificaciones entran en vigor desde su
            publicación en esta página.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">10. Contacto</h2>
          <p className="text-muted-foreground">
            Para comunicaciones legales, utiliza el <a className="text-primary hover:underline" href="/contact">formulario de contacto</a>
            o la dirección postal indicada en el apartado “Titular del sitio”.
          </p>
        </section>

        <section>
          <h2 className="text-2xl font-bold text-foreground mb-4">11. Ley aplicable y jurisdicción</h2>
          <p className="text-muted-foreground">
            Estas condiciones se rigen por la legislación española. Las partes se someten a los Juzgados y Tribunales de
            Almería (España), con renuncia a cualquier otro fuero.
          </p>
        </section>
      </div>
    </div>
  );
}
