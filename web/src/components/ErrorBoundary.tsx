import React from 'react';

type ErrorBoundaryState = {
  hasError: boolean;
  message?: string;
  stack?: string | null;
};

export class ErrorBoundary extends React.Component<React.PropsWithChildren, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false };

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, message: error.message };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo) {
    // Surface render errors in console for quick diagnosis.
    console.error('UI crash', error, info);
    this.setState({ stack: info.componentStack });
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-[40vh] flex items-center justify-center">
          <div className="bg-muted/30 border border-dashed border-muted-foreground/30 rounded-xl p-8 text-center max-w-xl">
            <p className="text-muted-foreground font-semibold mb-2">Se produjo un error al renderizar la vista.</p>
            {this.state.message && (
              <p className="text-sm text-muted-foreground break-words">{this.state.message}</p>
            )}
            {this.state.stack && (
              <pre className="mt-4 text-left text-xs text-muted-foreground whitespace-pre-wrap">
                {this.state.stack}
              </pre>
            )}
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
