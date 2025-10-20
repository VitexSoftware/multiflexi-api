# WARP.md - Working AI Reference for MultiFlexi API

## Project Overview
**Type**: Node.js API Code Generator
**Purpose**: OpenAPI-based API client/server generator for MultiFlexi ecosystem
**Status**: Active
**Repository**: https://github.com/VitexSoftware/multiflexi-api

## Key Technologies
- Node.js
- OpenAPI Generator
- npm/yarn
- TypeScript (generated code)

## Architecture & Structure
```
multiflexi-api/
├── openapi.yaml         # API specification
├── generate.js          # Code generation script
├── templates/           # Custom templates for generation
├── generated/           # Generated API code
├── docs/                # API documentation
└── package.json         # Node.js configuration
```

## Development Workflow

### Prerequisites
- Development environment setup
- Required dependencies

### Setup Instructions
```bash
# Clone the repository
git clone git@github.com:VitexSoftware/multiflexi-api.git
cd multiflexi-api

# Install dependencies
npm install
```

### Build & Run
```bash
npm run build
```

### Testing
```bash
npm test
```

## Key Concepts
- **Main Components**: Core functionality and modules
- **Configuration**: Configuration files and environment variables
- **Integration Points**: External services and dependencies

## Common Tasks

### Development
- Review code structure
- Implement new features
- Fix bugs and issues

### Deployment
- Build and package
- Deploy to target environment
- Monitor and maintain

## Troubleshooting
- **Common Issues**: Check logs and error messages
- **Debug Commands**: Use appropriate debugging tools
- **Support**: Check documentation and issue tracker

## Additional Notes
- Project-specific conventions
- Development guidelines
- Related documentation
